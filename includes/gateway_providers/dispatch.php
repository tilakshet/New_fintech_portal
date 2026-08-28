<?php
/**
 * Provider-agnostic entry point for creating a real payment order.
 * Callers (deposits/create.php) don't need to know which provider a
 * gateway is - they call create_gateway_order() and handle exactly two
 * exception types, same as before this file existed. Adding a third
 * provider means adding a case here, not touching the caller.
 */

require_once __DIR__ . '/razorpay.php';
require_once __DIR__ . '/cashfree.php';

/** Thrown when we don't know if the order was actually created - never treat as a definite failure. */
class GatewayOrderAmbiguousException extends RuntimeException {}

/** A definite failure whose message is safe and useful to show the customer verbatim (e.g. "add a phone number") - unlike an opaque provider rejection reason, which callers should NOT surface as-is. */
class GatewayCustomerActionRequiredException extends RuntimeException {}

function gateway_supports_live_order_creation(array $gateway): bool
{
    return in_array($gateway['provider'], ['razorpay', 'cashfree'], true)
        && !empty($gateway['public_key'])
        && !empty($gateway['api_key_encrypted']);
}

/**
 * Creates a real order at whichever provider this gateway is.
 *
 * @return array{gateway_txn_id: string, checkout: array} checkout is a
 *   provider-shaped payload the frontend uses to launch that provider's
 *   widget - always includes a "provider" key so the frontend can branch.
 * @throws GatewayOrderAmbiguousException see class doc.
 * @throws RuntimeException on a definite, synchronous rejection.
 */
function create_gateway_order(array $gateway, string $reference, string $amountRupees, array $user, ?string $customerPhone): array
{
    switch ($gateway['provider']) {
        case 'razorpay':
            try {
                $order = razorpay_create_order($gateway, $reference, $amountRupees, 'INR');
            } catch (RazorpayAmbiguousException $e) {
                throw new GatewayOrderAmbiguousException($e->getMessage(), 0, $e);
            }
            return [
                'gateway_txn_id' => $order['order_id'],
                'checkout' => [
                    'provider' => 'razorpay',
                    'order_id' => $order['order_id'],
                    'key_id' => $order['key_id'],
                    'amount' => $order['amount_paise'],
                    'currency' => 'INR',
                ],
            ];

        case 'cashfree':
            if (empty($customerPhone)) {
                throw new GatewayCustomerActionRequiredException('Your profile is missing a phone number, which Cashfree requires to start a payment. Add one in Settings and try again.');
            }
            try {
                $order = cashfree_create_order($gateway, $reference, $amountRupees, [
                    'id' => 'user' . $user['id'],
                    'phone' => $customerPhone,
                    'email' => $user['email'],
                    'name' => $user['name'],
                ], 'INR');
            } catch (CashfreeAmbiguousException $e) {
                throw new GatewayOrderAmbiguousException($e->getMessage(), 0, $e);
            }
            return [
                'gateway_txn_id' => $order['cf_order_id'] ?? $order['order_id'],
                'checkout' => [
                    'provider' => 'cashfree',
                    'payment_session_id' => $order['payment_session_id'],
                    'order_id' => $order['order_id'],
                    'environment' => !empty($gateway['sandbox_mode']) ? 'sandbox' : 'production',
                ],
            ];

        default:
            throw new RuntimeException("No live integration exists for provider '{$gateway['provider']}'.");
    }
}
