<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/partner_auth.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int) ($input['user_id'] ?? 0);

if ($userId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}

$pdo = db();

$userStmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ? AND role = "customer"');
$userStmt->execute([$userId]);
$customer = $userStmt->fetch();
if (!$customer) {
    json_response(false, null, 'Customer not found.', 404);
}

$token = generate_client_api_token();

// One row per customer: create it the first time, rotate it every time
// after. Rotating always immediately invalidates whatever the previous
// token was, since selection is by token_hash lookup — there is no
// grace period where both the old and new token work.
$existingStmt = $pdo->prepare('SELECT id, client_key FROM client_api_credentials WHERE user_id = ?');
$existingStmt->execute([$userId]);
$existing = $existingStmt->fetch();

if ($existing) {
    $pdo->prepare(
        'UPDATE client_api_credentials
         SET token_hash = ?, token_last4 = ?, status = "active", generated_at = UTC_TIMESTAMP(), revoked_at = NULL
         WHERE id = ?'
    )->execute([$token['token_hash'], $token['token_last4'], $existing['id']]);
    $clientKey = $existing['client_key'];
    $action = 'api_client_token_rotated';
} else {
    $clientKey = 'ck_live_' . bin2hex(random_bytes(6));
    $pdo->prepare(
        'INSERT INTO client_api_credentials (user_id, client_key, token_hash, token_last4, status, generated_at)
         VALUES (?, ?, ?, ?, "active", UTC_TIMESTAMP())'
    )->execute([$userId, $clientKey, $token['token_hash'], $token['token_last4']]);
    $action = 'api_client_token_generated';
}

write_audit_log((int) $actor['id'], $action, 'user', $userId, ['client_key' => $clientKey]);

json_response(true, [
    'client_key' => $clientKey,
    'token' => $token['token'], // shown exactly once — the admin must copy it now
    'token_last4' => $token['token_last4'],
], 'API key generated. Copy it now — it will not be shown again.');