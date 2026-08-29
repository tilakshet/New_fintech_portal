<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$payload = [
    'iss' => 'verapay',
    'sub' => (string) $user['id'],
    'iat' => time(),
    'exp' => time() + (365 * 24 * 60 * 60),
    'jti' => bin2hex(random_bytes(8)),
];

$token = jwt_encode($payload, PLATFORM_JWT_SECRET);

$pdo = db();
$stmt = $pdo->prepare(
    'UPDATE customer_api_credentials SET bearer_token = ?, bearer_token_generated_at = NOW() WHERE user_id = ?'
);
$stmt->execute([$token, $user['id']]);

if ($stmt->rowCount() === 0) {
    json_response(false, null, 'API credentials have not been provisioned yet. Reload the page and try again.', 409);
}

// Overwriting the stored token here is what actually revokes the previous
// one — authenticate_via_bearer_token() only accepts a token that exactly
// matches what's currently stored, so the old JWT stops working
// immediately even though it wouldn't otherwise expire for up to a year.
write_audit_log((int) $user['id'], 'api_bearer_token_generated', 'user', (int) $user['id'], []);

json_response(true, ['bearer_token' => $token], 'API token generated. Any previous token stopped working immediately.');
