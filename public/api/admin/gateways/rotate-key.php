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
$apiKey = trim((string) ($input['api_key'] ?? ''));

if ($id <= 0) {
    json_response(false, null, 'A gateway is required.', 422);
}
if (mb_strlen($apiKey) < 8) {
    json_response(false, null, 'Enter an API key of at least 8 characters.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, display_name FROM payment_gateways WHERE id = ?');
$stmt->execute([$id]);
$gateway = $stmt->fetch();

if (!$gateway) {
    json_response(false, null, 'Gateway not found.', 404);
}

$last4 = mb_substr($apiKey, -4);
$hash = password_hash($apiKey, PASSWORD_DEFAULT);
$pdo->prepare('UPDATE payment_gateways SET api_key_last4 = ?, api_key_hash = ? WHERE id = ?')->execute([$last4, $hash, $id]);

write_audit_log((int) $actor['id'], 'gateway_key_rotated', 'payment_gateway', $id, []);

json_response(true, ['api_key_last4' => $last4], "{$gateway['display_name']}'s key has been rotated.");
