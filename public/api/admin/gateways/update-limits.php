<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/money.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($input['id'] ?? 0);
$priorityRaw = $input['priority'] ?? null;
$limitRaw = array_key_exists('daily_limit_amount', $input) ? $input['daily_limit_amount'] : false;

if ($id <= 0) {
    json_response(false, null, 'A gateway is required.', 422);
}
if (!is_numeric($priorityRaw) || (int) $priorityRaw < 0 || (int) $priorityRaw > 9999) {
    json_response(false, null, 'Priority must be a number between 0 and 9999. Lower numbers are tried first.', 422);
}
if ($limitRaw === false) {
    json_response(false, null, 'daily_limit_amount is required — send null for no limit.', 422);
}

$dailyLimit = null;
if ($limitRaw !== null && $limitRaw !== '') {
    $dailyLimit = sanitize_amount($limitRaw);
    if ($dailyLimit === null || money_cmp($dailyLimit, '0.00') < 0) {
        json_response(false, null, 'Enter a valid daily limit amount, or leave it blank for unlimited.', 422);
    }
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, display_name FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}

$pdo->prepare('UPDATE payment_gateways SET priority = ?, daily_limit_amount = ? WHERE id = ?')
    ->execute([(int) $priorityRaw, $dailyLimit, $id]);

write_audit_log((int) $actor['id'], 'gateway_limits_updated', 'payment_gateway', $id, [
    'priority' => (int) $priorityRaw,
    'daily_limit_amount' => $dailyLimit,
]);

json_response(true, [
    'priority' => (int) $priorityRaw,
    'daily_limit_amount' => $dailyLimit,
], "{$gateway['display_name']}'s limits have been updated.");
