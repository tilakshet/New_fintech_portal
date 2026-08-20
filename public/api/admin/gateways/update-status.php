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
$status = $input['status'] ?? '';

if ($id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
    json_response(false, null, 'A gateway and a valid status are required.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, display_name, is_default FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}
if ($status === 'inactive' && (int) $gateway['is_default'] === 1) {
    json_response(false, null, 'Set another gateway as default before deactivating this one.', 422);
}

$pdo->prepare('UPDATE payment_gateways SET status = ? WHERE id = ?')->execute([$status, $id]);
write_audit_log((int) $actor['id'], 'gateway_status_changed', 'payment_gateway', $id, ['status' => $status]);

json_response(true, null, "{$gateway['display_name']} is now {$status}.");
