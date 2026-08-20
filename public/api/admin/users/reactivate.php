<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$targetId = (int) ($input['user_id'] ?? 0);

if ($targetId <= 0) {
    json_response(false, null, 'A user is required.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, email, status FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    json_response(false, null, 'User not found.', 404);
}
if ($target['status'] === 'active') {
    json_response(false, null, 'This user is already active.', 422);
}

$pdo->prepare('UPDATE users SET status = "active" WHERE id = ?')->execute([$targetId]);

$pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "security", "Account reactivated", "Your account has been reactivated. You can sign in again.")')
    ->execute([$targetId]);

write_audit_log((int) $actor['id'], 'user_reactivated', 'user', $targetId, ['target_email' => $target['email']]);

json_response(true, null, "{$target['name']}'s account has been reactivated.");
