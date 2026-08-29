<?php
/**
 * Cashfree-specific outbound integration: order creation (pay-in) and
 * inbound webhook signature/payload handling.
 *
 * API reference used (confirmed against Cashfree's published docs):
 * POST https://sandbox.cashfree.com/pg/orders (or api.cashfree.com/pg/orders
 * for production - Cashfree, unlike Razorpay, uses genuinely separate base
 * URLs per environment). Auth via x-client-id/x-client-secret/x-api-version
 * headers, not Basic Auth. customer_details.customer_phone is a REQUIRED
 * field Cashfree rejects orders without - see gateway_providers/dispatch.php
 * for where that's sourced from and how a missing phone is handled.
 *
 * Webhook signing: Base64Encode(HMAC-SHA256(x-webhook-timestamp . rawBody,
 * secret)) - note this concatenates the timestamp header directly onto the
 * raw body (no separator) before signing, and the header carrying the
 * webhook secret's counterpart is x-webhook-signature. Confirmed via
 * Cashfree's docs; distinct from Razorpay's simpler HMAC(rawBody) scheme.
 */

require_once __DIR__ . '/../gateway_secrets.php';

const CASHFREE_API_BASE_SANDBOX = 'https://sandbox.cashfree.com/pg';
const CASHFREE_API_BASE_PRODUCTION = 'https://api.cashfree.com/pg';
const CASHFREE_API_VERSION = '2023-08-01';

/** Thrown when we could not confirm whether Cashfree actually created the order or not. */
class CashfreeAmbiguousException extends RuntimeException {}

/**
 * Creates a Cashfree order for a pay-in (deposit).
 *
 * @param array $gateway payment_gateways row - public_key holds the Client ID, api_key_encrypted the Client Secret, sandbox_mode picks the base URL.
 * @param array $customer must have 'id', 'phone' (required by Cashfree); 'email'/'name' optional.
 * @return array{order_id: string, cf_order_id: ?string, payment_session_id: string}
 * @throws CashfreeAmbiguousException on a network-level failure.
 * @throws RuntimeException on a definite, synchronous rejection, or missing required customer data.
 */
function cashfree_create_order(array $gateway, string $reference, string $amountRupees, array $customer, string $currency = 'INR'): array
{
    $clientId = trim((string) ($gateway['public_key'] ?? ''));
    if ($clientId === '' || empty($gateway['api_key_encrypted'])) {
        throw new RuntimeException('This gateway is missing its Cashfree Client ID or Client Secret.');
    }
    if (empty($customer['phone'])) {
        throw new RuntimeException('Your profile is missing a phone number, which Cashfree requires to start a payment. Add one in Settings and try again.');
    }

    $clientSecret = gateway_decrypt_secret($gateway['api_key_encrypted']);

    $payload = json_encode([
        'order_id' => $reference,
        'order_amount' => (float) $amountRupees,
        'order_currency' => $currency,
        'customer_details' => array_filter([
            'customer_id' => $customer['id'],
            'customer_phone' => $customer['phone'],
            'customer_email' => $customer['email'] ?? null,
            'customer_name' => $customer['name'] ?? null,
        ]),
    ]);

    $base = !empty($gateway['sandbox_mode']) ? CASHFREE_API_BASE_SANDBOX : CASHFREE_API_BASE_PRODUCTION;

    $ch = curl_init($base . '/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-client-id: ' . $clientId,
            'x-client-secret: ' . $clientSecret,
            'x-api-version: ' . CASHFREE_API_VERSION,
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErrno !== 0) {
        throw new CashfreeAmbiguousException("Network error contacting Cashfree ({$curlError}) - order status unknown.");
    }

    $decoded = json_decode((string) $response, true);

    if ($httpStatus >= 200 && $httpStatus < 300 && isset($decoded['payment_session_id'])) {
        return [
            'order_id' => $decoded['order_id'] ?? $reference,
            'cf_order_id' => isset($decoded['cf_order_id']) ? (string) $decoded['cf_order_id'] : null,
            'payment_session_id' => $decoded['payment_session_id'],
        ];
    }

    $reason = $decoded['message'] ?? "unexpected HTTP {$httpStatus}";
    throw new RuntimeException("Cashfree rejected the order: {$reason}");
}

function cashfree_verify_webhook_signature(string $rawBody, string $timestampHeader, string $signatureHeader, string $secret): bool
{
    if ($timestampHeader === '' || $signatureHeader === '' || $secret === '') {
        return false;
    }
    $expected = base64_encode(hash_hmac('sha256', $timestampHeader . $rawBody, $secret, true));
    return hash_equals($expected, trim($signatureHeader));
}

/**
 * Maps Cashfree's webhook shape into the generic
 * {event_id, reference, gateway_txn_id, status} shape
 * process_gateway_webhook() understands. Unlike Razorpay, Cashfree's
 * data.order.order_id IS the merchant-supplied order_id we sent at
 * creation time (our own reference) - so this correlates by `reference`
 * directly, the same path the generic receiver already uses.
 */
function cashfree_parse_webhook_payload(array $payload): ?array
{
    $type = $payload['type'] ?? '';
    $order = $payload['data']['order'] ?? null;
    $payment = $payload['data']['payment'] ?? null;

    $statusMap = [
        'PAYMENT_SUCCESS_WEBHOOK' => 'success',
        'PAYMENT_FAILED_WEBHOOK' => 'failed',
        'PAYMENT_USER_DROPPED_WEBHOOK' => 'failed',
    ];

    if (!$order || !$payment || !isset($statusMap[$type]) || empty($order['order_id'])) {
        return null;
    }

    $paymentId = (string) ($payment['cf_payment_id'] ?? '');
    if ($paymentId === '') {
        return null;
    }

    return [
        // Cashfree doesn't send a distinct event/delivery id - a given
        // payment reaching a given terminal status is itself a stable,
        // naturally-deduplicating identifier for redelivery.
        'event_id' => "{$paymentId}:{$type}",
        'reference' => (string) $order['order_id'],
        'gateway_txn_id' => $paymentId,
        'status' => $statusMap[$type],
    ];
}
