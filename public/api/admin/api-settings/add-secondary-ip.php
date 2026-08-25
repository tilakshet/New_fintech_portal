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

try {
    $pdo->prepare('INSERT INTO platform_whitelisted_ips (ip_address) VALUES (?)')->execute([$ip]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_response(false, null, 'That IP address is already whitelisted.', 409);
    }
    error_log('[api-settings/add-secondary-ip] ' . $e->getMessage());
    json_response(false, null, 'Unable to add that IP right now. Please try again.', 500);
}

write_audit_log((int) $user['id'], 'platform_secondary_ip_added', 'platform_whitelisted_ips', (int) $pdo->lastInsertId(), ['ip_address' => $ip]);

json_response(true, ['id' => (int) $pdo->lastInsertId(), 'ip_address' => $ip], 'IP added to whitelist.');
