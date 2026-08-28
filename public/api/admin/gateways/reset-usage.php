<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    json_response(false, null, 'A gateway is required.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, display_name FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}

$pdo->prepare('UPDATE gateway_daily_usage SET used_amount = 0.00, transaction_count = 0 WHERE gateway_id = ? AND usage_date = ?')
    ->execute([$id, gmdate('Y-m-d')]);

write_audit_log((int) $actor['id'], 'gateway_usage_reset', 'payment_gateway', $id, ['usage_date' => gmdate('Y-m-d')]);

json_response(true, null, "{$gateway['display_name']}'s usage for today has been reset.");
