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
$stmt = $pdo->prepare('SELECT id, display_name, status FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}
if ($gateway['status'] !== 'active') {
    json_response(false, null, 'Activate this gateway before making it the default.', 422);
}

$pdo->beginTransaction();
try {
    $pdo->exec('UPDATE payment_gateways SET is_default = 0');
    $pdo->prepare('UPDATE payment_gateways SET is_default = 1 WHERE id = ?')->execute([$id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[gateways/set-default] ' . $e->getMessage());
    json_response(false, null, 'Unable to update the default gateway. Please try again.', 500);
}

write_audit_log((int) $actor['id'], 'gateway_set_default', 'payment_gateway', $id, []);

json_response(true, null, "{$gateway['display_name']} is now the default gateway.");
