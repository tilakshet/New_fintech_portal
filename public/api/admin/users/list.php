<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = require_auth();
require_role($user, 'admin', 'operator');
$pdo = db();

[$page, $perPage, $offset] = paginate_params(20, 100);

$where = ["u.role != 'admin'"];
$params = [];
if (in_array($_GET['status'] ?? '', ['active', 'suspended'], true)) {
    $where[] = 'u.status = :status';
    $params['status'] = $_GET['status'];
}
if (!empty($_GET['search'])) {
    // Real (non-emulated) prepared statements require a distinct bound
    // parameter per placeholder occurrence, even when the value repeats.
    $where[] = '(u.name LIKE :search1 OR u.email LIKE :search2)';
    $params['search1'] = $params['search2'] = '%' . $_GET['search'] . '%';
}

$whereSql = implode(' AND ', $where);
$totalDocTypes = count(kyc_document_types());

$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.avatar_initials, u.created_at,
        COUNT(k.id) AS kyc_uploaded_count,
        SUM(CASE WHEN k.status = 'pending' THEN 1 ELSE 0 END) AS kyc_pending_count,
        SUM(CASE WHEN k.status = 'verified' THEN 1 ELSE 0 END) AS kyc_verified_count
     FROM users u
     LEFT JOIN kyc_documents k ON k.user_id = u.id
     WHERE {$whereSql}
     GROUP BY u.id, u.name, u.email, u.role, u.status, u.avatar_initials, u.created_at
     ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = array_map(static function (array $row) use ($totalDocTypes): array {
    $row['kyc_uploaded_count'] = (int) $row['kyc_uploaded_count'];
    $row['kyc_pending_count'] = (int) $row['kyc_pending_count'];
    $row['kyc_verified_count'] = (int) $row['kyc_verified_count'];
    $row['kyc_total_types'] = $totalDocTypes;
    return $row;
}, $stmt->fetchAll());

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
foreach ($params as $key => $value) {
    $countStmt->bindValue(":{$key}", $value);
}
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();

json_response(true, [
    'users' => $users,
    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)],
], 'ok');