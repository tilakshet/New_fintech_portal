<?php
/**
 * A customer's own API credentials (Settings → API access). Auto-provisions
 * client_key/secret on first load, same pattern the old platform-wide
 * settings screen used — just scoped to one row per user now instead of a
 * singleton. Also returns the IP whitelist admin has approved for this
 * account, read-only here since only admin can change it.
 */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM customer_api_credentials WHERE user_id = ?');
$stmt->execute([$user['id']]);
$creds = $stmt->fetch();

// Only non-null the one time this request is the one that generated it —
// like every other secret in this app, the plaintext is shown exactly
// once and only the hash persists after. A later GET (page refresh, etc.)
// correctly gets secret_key_plaintext: null even though secret_key_masked
// is always populated.
$secretKeyPlaintext = null;

if (!$creds) {
    $clientKey = 'VP' . strtoupper(bin2hex(random_bytes(5)));
    $secretKeyPlaintext = bin2hex(random_bytes(16));

    $pdo->prepare(
        'INSERT INTO customer_api_credentials (user_id, client_key, secret_key_hash, secret_key_last4)
         VALUES (?, ?, ?, ?)'
    )->execute([$user['id'], $clientKey, password_hash($secretKeyPlaintext, PASSWORD_DEFAULT), substr($secretKeyPlaintext, -4)]);

    write_audit_log((int) $user['id'], 'api_credentials_provisioned', 'user', (int) $user['id'], []);

    $stmt->execute([$user['id']]);
    $creds = $stmt->fetch();
}

$ipsStmt = $pdo->prepare('SELECT ip_address, created_at FROM customer_whitelisted_ips WHERE user_id = ? ORDER BY created_at ASC');
$ipsStmt->execute([$user['id']]);

json_response(true, [
    'client_key' => $creds['client_key'],
    'secret_key_masked' => '••••' . $creds['secret_key_last4'],
    'secret_key_plaintext' => $secretKeyPlaintext,
    'bearer_token' => $creds['bearer_token'],
    'bearer_token_generated_at' => $creds['bearer_token_generated_at'],
    'payout_callback_url' => $creds['payout_callback_url'],
    'payin_callback_url' => $creds['payin_callback_url'],
    'whitelisted_ips' => $ipsStmt->fetchAll(),
], 'ok');
