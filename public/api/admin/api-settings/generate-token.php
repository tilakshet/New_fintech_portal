<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$payload = [
    'iss' => 'verapay',
    'sub' => 'platform-api',
    'iat' => time(),
    'exp' => time() + (365 * 24 * 60 * 60),
    'jti' => bin2hex(random_bytes(8)),
];

$token = jwt_encode($payload, PLATFORM_JWT_SECRET);

$pdo = db();
$stmt = $pdo->prepare(
    'UPDATE platform_api_settings SET bearer_token = ?, bearer_token_generated_at = NOW() WHERE id = 1'
);
$stmt->execute([$token]);

if ($stmt->rowCount() === 0) {
    json_response(false, null, 'API credentials have not been provisioned yet. Reload the page and try again.', 409);
}

write_audit_log((int) $user['id'], 'platform_bearer_token_generated', 'platform_api_settings', 1, []);

json_response(true, ['bearer_token' => $token], 'Bearer token generated.');
