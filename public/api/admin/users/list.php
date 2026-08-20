<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = require_auth();
require_role($user, 'admin', 'operator');
$pdo = db();

[$page, $perPage, $offset] = paginate_params(20, 100);

$where = ["role != 'admin'"];
$params = [];

if (in_array($_GET['status'] ?? '', ['active', 'suspended'], true)) {
    $where[] = 'status = :status';
    $params['status'] = $_GET['status'];
}
if (!empty($_GET['search'])) {
    // Real (non-emulated) prepared statements require a distinct bound
    // parameter per placeholder occurrence, even when the value repeats.
    $where[] = '(name LIKE :search1 OR email LIKE :search2)';
    $params['search1'] = $params['search2'] = '%' . $_GET['search'] . '%';
}

$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT id, name, email, role, status, avatar_initials, created_at FROM users WHERE {$whereSql} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereSql}");
foreach ($params as $key => $value) {
    $countStmt->bindValue(":{$key}", $value);
}
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();

json_response(true, [
    'users' => $users,
    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)],
], 'ok');
