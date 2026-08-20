<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = require_auth();
$pdo = db();

$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

$stmt = $pdo->prepare('SELECT id, type, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?');
$stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->execute();

json_response(true, ['notifications' => $stmt->fetchAll()], 'ok');
