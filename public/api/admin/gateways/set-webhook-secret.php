<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/gateway_secrets.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($input['id'] ?? 0);
$secret = trim((string) ($input['webhook_secret'] ?? ''));

if ($id <= 0) {
    json_response(false, null, 'A gateway is required.', 422);
}
if (mb_strlen($secret) < 16) {
    json_response(false, null, 'Enter a webhook signing secret of at least 16 characters — use whatever the provider issues for this purpose.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, display_name FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}

try {
    $encrypted = gateway_encrypt_secret($secret);
} catch (Throwable $e) {
    error_log('[gateways/set-webhook-secret] ' . $e->getMessage());
    json_response(false, null, 'Gateway encryption is not configured on this server. Set GATEWAY_ENCRYPTION_KEY and try again.', 500);
}

$pdo->prepare('UPDATE payment_gateways SET webhook_secret_encrypted = ? WHERE id = ?')->execute([$encrypted, $id]);

write_audit_log((int) $actor['id'], 'gateway_webhook_secret_updated', 'payment_gateway', $id, []);

$webhookUrl = APP_URL . "/api/webhooks/gateway.php?gateway_id={$id}";
json_response(true, ['webhook_url' => $webhookUrl], "{$gateway['display_name']}'s webhook secret has been saved. Configure {$webhookUrl} in the provider's dashboard.");
