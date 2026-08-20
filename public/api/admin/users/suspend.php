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
if ($targetId === (int) $actor['id']) {
    json_response(false, null, 'You cannot suspend your own account.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ?');
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) {
    json_response(false, null, 'User not found.', 404);
}
if ($target['role'] === 'admin') {
    json_response(false, null, 'Administrator accounts cannot be suspended.', 422);
}
if ($target['status'] === 'suspended') {
    json_response(false, null, 'This user is already suspended.', 422);
}

// This is the entire mechanism that invalidates the user's live session:
// require_auth() re-reads this status on their very next request.
$pdo->prepare('UPDATE users SET status = "suspended" WHERE id = ?')->execute([$targetId]);

$pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "security", "Account suspended", "Your account has been suspended. Contact support for assistance.")')
    ->execute([$targetId]);

write_audit_log((int) $actor['id'], 'user_suspended', 'user', $targetId, ['target_email' => $target['email']]);

json_response(true, null, "{$target['name']}'s account has been suspended.");
