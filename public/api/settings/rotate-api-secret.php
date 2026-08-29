<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$secretKey = bin2hex(random_bytes(16));

$pdo = db();
$stmt = $pdo->prepare(
    'UPDATE customer_api_credentials SET secret_key_hash = ?, secret_key_last4 = ? WHERE user_id = ?'
);
$stmt->execute([password_hash($secretKey, PASSWORD_DEFAULT), substr($secretKey, -4), $user['id']]);

if ($stmt->rowCount() === 0) {
    json_response(false, null, 'API credentials have not been provisioned yet. Reload the page and try again.', 409);
}

write_audit_log((int) $user['id'], 'api_secret_rotated', 'user', (int) $user['id'], []);

// The only moment the plaintext secret is ever available again — only the
// hash is stored from here on, matching every other secret in this app.
json_response(true, ['secret_key' => $secretKey], 'Secret key rotated. Copy it now — it will not be shown again. The previous one no longer works.');
