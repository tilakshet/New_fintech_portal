<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$pdo = db();

$stmt = $pdo->query('SELECT * FROM platform_api_settings WHERE id = 1');
$settings = $stmt->fetch();

if (!$settings) {
    $clientKey = 'VP' . strtoupper(bin2hex(random_bytes(5)));
    $secretKey = bin2hex(random_bytes(16));

    $pdo->prepare(
        'INSERT INTO platform_api_settings (id, client_key, secret_key_hash, secret_key_last4)
         VALUES (1, ?, ?, ?)'
    )->execute([$clientKey, password_hash($secretKey, PASSWORD_DEFAULT), substr($secretKey, -4)]);

    write_audit_log((int) $user['id'], 'platform_api_credentials_provisioned', 'platform_api_settings', 1, []);

    $stmt = $pdo->query('SELECT * FROM platform_api_settings WHERE id = 1');
    $settings = $stmt->fetch();
}

$ipsStmt = $pdo->query('SELECT id, ip_address FROM platform_whitelisted_ips ORDER BY created_at ASC');
$secondaryIps = $ipsStmt->fetchAll();

json_response(true, [
    'client_key' => $settings['client_key'],
    'secret_key_masked' => '••••' . $settings['secret_key_last4'],
    'bearer_token' => $settings['bearer_token'],
    'bearer_token_generated_at' => $settings['bearer_token_generated_at'],
    'primary_whitelist_ip' => $settings['primary_whitelist_ip'],
    'secondary_ips' => $secondaryIps,
    'payout_callback_url' => $settings['payout_callback_url'],
    'payin_callback_url' => $settings['payin_callback_url'],
], 'ok');
