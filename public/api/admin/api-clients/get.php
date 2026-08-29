<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}

$pdo = db();

$userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? AND role = "customer"');
$userStmt->execute([$userId]);
$customer = $userStmt->fetch();
if (!$customer) {
    json_response(false, null, 'Customer not found.', 404);
}

$credStmt = $pdo->prepare('SELECT * FROM client_api_credentials WHERE user_id = ?');
$credStmt->execute([$userId]);
$credential = $credStmt->fetch();

$whitelistedIps = [];
if ($credential) {
    $ipsStmt = $pdo->prepare('SELECT ip_address FROM client_whitelisted_ips WHERE credential_id = ? ORDER BY id ASC');
    $ipsStmt->execute([$credential['id']]);
    $whitelistedIps = $ipsStmt->fetchAll(PDO::FETCH_COLUMN);
}

json_response(true, [
    'customer' => $customer,
    'credential' => $credential ? [
        'client_key' => $credential['client_key'],
        'token_last4' => $credential['token_last4'],
        'status' => $credential['status'],
        'payin_callback_url' => $credential['payin_callback_url'],
        'payout_callback_url' => $credential['payout_callback_url'],
        'generated_at' => $credential['generated_at'],
        'revoked_at' => $credential['revoked_at'],
    ] : null,
    'whitelisted_ips' => $whitelistedIps,
]);