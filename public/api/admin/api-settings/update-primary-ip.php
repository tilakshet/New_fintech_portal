<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$ip = trim((string) ($input['ip_address'] ?? ''));

if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
    json_response(false, null, 'Enter a valid IPv4 or IPv6 address.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('UPDATE platform_api_settings SET primary_whitelist_ip = ? WHERE id = 1');
$stmt->execute([$ip]);

if ($stmt->rowCount() === 0) {
    $exists = $pdo->query('SELECT 1 FROM platform_api_settings WHERE id = 1')->fetchColumn();
    if (!$exists) {
        json_response(false, null, 'API credentials have not been provisioned yet. Reload the page and try again.', 409);
    }
}

write_audit_log((int) $user['id'], 'platform_primary_ip_updated', 'platform_api_settings', 1, ['ip_address' => $ip]);

json_response(true, ['primary_whitelist_ip' => $ip], 'Primary whitelist IP updated.');
