<?php
/**
 * Inbound payment gateway webhook receiver.
 *
 * Not session/CSRF-guarded — the caller is a payment provider's server,
 * not a logged-in browser. The per-gateway webhook secret (verified via
 * X-Webhook-Signature below) is what authenticates the request instead.
 * See includes/gateway_webhooks.php for why the signature scheme and
 * payload shape here are provider-agnostic placeholders that must be
 * replaced with the real provider's actual scheme before going live.
 *
 * Configure this URL (with this gateway's id) in the provider's dashboard:
 *   {APP_URL}/api/webhooks/gateway.php?gateway_id={id}
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/gateway_secrets.php';
require_once __DIR__ . '/../../../includes/gateway_webhooks.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$gatewayId = (int) ($_GET['gateway_id'] ?? 0);
if ($gatewayId <= 0) {
    json_response(false, null, 'gateway_id is required.', 400);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, status, webhook_secret_encrypted FROM payment_gateways WHERE id = ?');
$stmt->execute([$gatewayId]);
$gateway = $stmt->fetch();

if (!$gateway || $gateway['status'] !== 'active') {
    json_response(false, null, 'Unknown or inactive gateway.', 404);
}
if (!$gateway['webhook_secret_encrypted']) {
    error_log("[webhooks/gateway] gateway {$gatewayId} has no webhook secret configured");
    json_response(false, null, 'Webhook not configured for this gateway.', 409);
}

$rawBody = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

try {
    $secret = gateway_decrypt_secret($gateway['webhook_secret_encrypted']);
} catch (Throwable $e) {
    error_log('[webhooks/gateway] ' . $e->getMessage());
    json_response(false, null, 'Unable to verify this webhook right now.', 500);
}

if (!verify_generic_webhook_signature($rawBody, $signatureHeader, $secret)) {
    write_audit_log(null, 'webhook_signature_invalid', 'payment_gateway', $gatewayId, []);
    json_response(false, null, 'Invalid signature.', 401);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    json_response(false, null, 'Malformed JSON body.', 400);
}

$result = process_gateway_webhook($pdo, $gatewayId, $payload);
json_response($result['status'] < 400, null, $result['message'], $result['status']);
