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
$stmt = $pdo->prepare('SELECT id, display_name, is_default FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}
if ((int) $gateway['is_default'] === 1) {
    json_response(false, null, 'Set another gateway as default before removing this one.', 422);
}

$pdo->prepare('DELETE FROM payment_gateways WHERE id = ?')->execute([$id]);
write_audit_log((int) $actor['id'], 'gateway_deleted', 'payment_gateway', $id, ['display_name' => $gateway['display_name']]);

json_response(true, null, "{$gateway['display_name']} has been removed.");
