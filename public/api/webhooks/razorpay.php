<?php
/**
 * Razorpay webhook receiver. Verifies the real Razorpay signature scheme
 * and payload shape (see includes/gateway_providers/razorpay.php), then
 * hands off to the same provider-agnostic idempotency/settlement core
 * every other gateway uses (includes/gateway_webhooks.php) - nothing
 * about wallet crediting or duplicate-delivery handling is
 * provider-specific.
 *
 * Configure this URL in the Razorpay dashboard's webhook settings:
 *   {APP_URL}/api/webhooks/razorpay.php?gateway_id={id}
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/gateway_secrets.php';
require_once __DIR__ . '/../../../includes/gateway_webhooks.php';
require_once __DIR__ . '/../../../includes/gateway_providers/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$gatewayId = (int) ($_GET['gateway_id'] ?? 0);
if ($gatewayId <= 0) {
    json_response(false, null, 'gateway_id is required.', 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, provider, status, webhook_secret_encrypted FROM payment_gateways WHERE id = ?');
$stmt->execute([$gatewayId]);
$gateway = $stmt->fetch();

if (!$gateway || $gateway['status'] !== 'active' || $gateway['provider'] !== 'razorpay') {
    json_response(false, null, 'Unknown, inactive, or non-Razorpay gateway.', 404);
}
if (!$gateway['webhook_secret_encrypted']) {
    error_log("[webhooks/razorpay] gateway {$gatewayId} has no webhook secret configured");
    json_response(false, null, 'Webhook not configured for this gateway.', 409);
}

$rawBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

try {
    $secret = gateway_decrypt_secret($gateway['webhook_secret_encrypted']);
} catch (Throwable $e) {
    error_log('[webhooks/razorpay] ' . $e->getMessage());
    json_response(false, null, 'Unable to verify this webhook right now.', 500);
}

if (!razorpay_verify_webhook_signature($rawBody, $signatureHeader, $secret)) {
    write_audit_log(null, 'webhook_signature_invalid', 'payment_gateway', $gatewayId, ['provider' => 'razorpay']);
    json_response(false, null, 'Invalid signature.', 401);
}

$rawPayload = json_decode($rawBody, true);
if (!is_array($rawPayload)) {
    json_response(false, null, 'Malformed JSON body.', 400);
}

$mapped = razorpay_parse_webhook_payload($rawPayload);
if ($mapped === null) {
    // A Razorpay event type we don't act on (there are many) - acknowledge
    // without processing so Razorpay doesn't retry it.
    json_response(true, null, 'Event not actionable - ignored.', 200);
}

// Keep the full raw Razorpay payload in webhook_events for audit/debugging
// even though only the mapped fields drive the actual state transition.
$mapped['raw'] = $rawPayload;

$result = process_gateway_webhook($pdo, $gatewayId, $mapped);
json_response($result['status'] < 400, null, $result['message'], $result['status']);
