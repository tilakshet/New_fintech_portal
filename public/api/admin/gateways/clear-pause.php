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

$pdo->prepare('UPDATE payment_gateways SET consecutive_failures = 0, auto_paused_until = NULL WHERE id = ?')
    ->execute([$id]);

write_audit_log((int) $actor['id'], 'gateway_auto_pause_cleared', 'payment_gateway', $id, []);

json_response(true, null, "{$gateway['display_name']} is eligible for selection again.");