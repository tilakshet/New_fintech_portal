<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$name = trim((string) ($input['name'] ?? ''));

if ($name === '' || mb_strlen($name) > 120) {
    json_response(false, null, 'Enter a name (up to 120 characters).', 422);
}

$words = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
$initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1));

db()->prepare('UPDATE users SET name = ?, avatar_initials = ? WHERE id = ?')->execute([$name, $initials, $user['id']]);
write_audit_log((int) $user['id'], 'profile_updated', 'user', (int) $user['id'], []);

json_response(true, ['name' => $name, 'avatar_initials' => $initials], 'Profile updated.');
