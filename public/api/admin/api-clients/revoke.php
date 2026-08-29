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

if ($userId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM client_api_credentials WHERE user_id = ? AND status = "active"');
$stmt->execute([$userId]);
$credential = $stmt->fetch();

if (!$credential) {
    json_response(false, null, 'This customer has no active API key.', 404);
}

$pdo->prepare('UPDATE client_api_credentials SET status = "revoked", revoked_at = UTC_TIMESTAMP() WHERE id = ?')
    ->execute([$credential['id']]);

write_audit_log((int) $actor['id'], 'api_client_token_revoked', 'user', $userId, []);

json_response(true, null, 'API access revoked. This customer can no longer authenticate with the partner API.');