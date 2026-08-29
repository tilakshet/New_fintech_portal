<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int) ($input['user_id'] ?? 0);
$ip = trim((string) ($input['ip_address'] ?? ''));

if ($userId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}
if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
    json_response(false, null, 'Enter a valid IPv4 or IPv6 address.', 422);
}

$pdo = db();
$userStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ? AND role = "customer"');
$userStmt->execute([$userId]);
$targetUser = $userStmt->fetch();

if (!$targetUser) {
    json_response(false, null, 'Customer not found.', 404);
}

$stmt = $pdo->prepare('INSERT IGNORE INTO customer_whitelisted_ips (user_id, ip_address, added_by) VALUES (?, ?, ?)');
$stmt->execute([$userId, $ip, $actor['id']]);

if ($stmt->rowCount() === 0) {
    json_response(false, null, 'That IP is already whitelisted for this customer.', 422);
}

write_audit_log((int) $actor['id'], 'customer_api_ip_added', 'user', $userId, ['ip_address' => $ip]);

json_response(true, null, "{$ip} has been whitelisted for {$targetUser['name']}.", 201);
