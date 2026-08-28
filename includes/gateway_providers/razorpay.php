<?php
/**
 * Razorpay-specific outbound integration: order creation (pay-in) and
 * inbound webhook signature/payload handling.
 *
 * API reference used: POST https://api.razorpay.com/v1/orders (Basic Auth
 * key_id:key_secret; amount in integer paise; returns {"id": "order_..."})
 * and webhook signing (HMAC-SHA256 hex of the RAW body, keyed by the
 * webhook secret, in X-Razorpay-Signature). Confirmed against Razorpay's
 * published docs. What's NOT independently confirmed: whether Razorpay
 * copies an order's `notes` onto the resulting payment entity in webhook
 * payloads — so correlation here deliberately does NOT depend on that.
 * Instead the order_id we get back synchronously at creation time is
 * stored as transactions.gateway_txn_id immediately, and the webhook is
 * matched back to it via payload.payment.entity.order_id, which Razorpay's
 * docs do guarantee is present.
 *
 * No Razorpay SDK/Composer dependency - plain cURL, consistent with this
 * project's architecture.
 */

require_once __DIR__ . '/../gateway_secrets.php';

const RAZORPAY_API_BASE = 'https://api.razorpay.com/v1';

/** Thrown when we could not confirm whether Razorpay actually created the order or not (e.g. network timeout). Caller must NOT treat this as a definite failure. */
class RazorpayAmbiguousException extends RuntimeException {}

/**
 * Creates a Razorpay order for a pay-in (deposit).
 *
 * @param array $gateway payment_gateways row - must have public_key and api_key_encrypted set.
 * @return array{order_id: string, key_id: string, amount_paise: int}
 * @throws RazorpayAmbiguousException on a network-level failure - the order may or may not have been created.
 * @throws RuntimeException on a definite, synchronous rejection from Razorpay (safe to treat as "did not happen").
 */
function razorpay_create_order(array $gateway, string $reference, string $amountRupees, string $currency = 'INR'): array
{
    $keyId = trim((string) ($gateway['public_key'] ?? ''));
    if ($keyId === '' || empty($gateway['api_key_encrypted'])) {
        throw new RuntimeException('This gateway is missing its Razorpay key_id or key_secret.');
    }

    $keySecret = gateway_decrypt_secret($gateway['api_key_encrypted']);
    $amountPaise = (int) round(((float) $amountRupees) * 100);

    $payload = json_encode([
        'amount' => $amountPaise,
        'currency' => $currency,
        'receipt' => $reference,
    ]);

    $ch = curl_init(RAZORPAY_API_BASE . '/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERPWD => $keyId . ':' . $keySecret,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErrno !== 0) {
        throw new RazorpayAmbiguousException("Network error contacting Razorpay ({$curlError}) - order status unknown.");
    }

    $decoded = json_decode((string) $response, true);

    if ($httpStatus >= 200 && $httpStatus < 300 && isset($decoded['id'])) {
        return ['order_id' => $decoded['id'], 'key_id' => $keyId, 'amount_paise' => $amountPaise];
    }

    // A clean HTTP response (even an error one) means Razorpay definitely
    // responded - no order was created. Safe to treat as a definite failure.
    $reason = $decoded['error']['description'] ?? "unexpected HTTP {$httpStatus}";
    throw new RuntimeException("Razorpay rejected the order: {$reason}");
}

function razorpay_verify_webhook_signature(string $rawBody, string $signatureHeader, string $secret): bool
{
    if ($signatureHeader === '' || $secret === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, trim($signatureHeader));
}

/**
 * Maps Razorpay's actual webhook shape into the generic
 * {event_id, reference, gateway_txn_id, status} shape
 * process_gateway_webhook() understands. Correlates by order_id (via
 * gateway_txn_id), not by reference - see file header. Returns null for
 * event types this integration doesn't act on (Razorpay sends many).
 */
function razorpay_parse_webhook_payload(array $payload): ?array
{
    $event = $payload['event'] ?? '';
    $payment = $payload['payload']['payment']['entity'] ?? null;

    $statusMap = [
        'payment.captured' => 'success',
        'payment.failed' => 'failed',
    ];

    if (!$payment || !isset($statusMap[$event]) || empty($payment['order_id'])) {
        return null;
    }

    return [
        'event_id' => $payload['id'] ?? ($payment['id'] . ':' . $event),
        'reference' => null,
        'gateway_txn_id' => $payment['order_id'],
        'status' => $statusMap[$event],
    ];
}
