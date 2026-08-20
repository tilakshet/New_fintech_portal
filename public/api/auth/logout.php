<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

bootstrap_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard');
    exit;
}

$sentToken = $_POST['csrf_token'] ?? '';
if (hash_equals($_SESSION['csrf_token'] ?? '', $sentToken) && !empty($_SESSION['user_id'])) {
    write_audit_log((int) $_SESSION['user_id'], 'logout', 'user', (int) $_SESSION['user_id'], []);
}

destroy_session();
header('Location: /login');
exit;
