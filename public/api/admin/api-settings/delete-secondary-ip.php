<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    json_response(false, null, 'Invalid IP entry.', 422);
}

$stmt = db()->prepare('DELETE FROM platform_whitelisted_ips WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    json_response(false, null, 'That IP entry no longer exists.', 404);
}

write_audit_log((int) $user['id'], 'platform_secondary_ip_removed', 'platform_whitelisted_ips', $id, []);

json_response(true, null, 'IP removed from whitelist.');
