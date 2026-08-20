<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$pdo = db();

if (!empty($input['all'])) {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
    json_response(true, null, 'All notifications marked as read.');
}

$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    json_response(false, null, 'A notification is required.', 422);
}

// Scoped to user_id so a customer cannot mark another user's notification (IDOR guard).
$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);

json_response(true, null, 'Notification marked as read.');
