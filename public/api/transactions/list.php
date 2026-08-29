<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();
$isOperator = in_array($user['role'], ['admin', 'operator'], true);
$pdo = db();

[$page, $perPage, $offset] = paginate_params(20, 100);

$where = ['1=1'];
$params = [];

if (!$isOperator) {
    $where[] = 't.user_id = :uid';
    $params['uid'] = $user['id'];
}

if (in_array($_GET['type'] ?? '', ['deposit', 'withdrawal'], true)) {
    $where[] = 't.type = :type';
    $params['type'] = $_GET['type'];
}

if (in_array($_GET['status'] ?? '', ['pending', 'success', 'failed', 'cancelled', 'refunded'], true)) {
    $where[] = 't.status = :status';
    $params['status'] = $_GET['status'];
}

if (!empty($_GET['from'])) {
    $where[] = 'DATE(t.created_at) >= :from';
    $params['from'] = $_GET['from'];
}
if (!empty($_GET['to'])) {
    $where[] = 'DATE(t.created_at) <= :to';
    $params['to'] = $_GET['to'];
}

if (!empty($_GET['search'])) {
    // Real (non-emulated) prepared statements require a distinct bound
    // parameter per placeholder occurrence, even when the value repeats.
    $needle = '%' . $_GET['search'] . '%';
    if ($isOperator) {
        $where[] = '(t.reference LIKE :search1 OR u.name LIKE :search2 OR u.email LIKE :search3)';
        $params['search1'] = $params['search2'] = $params['search3'] = $needle;
    } else {
        $where[] = 't.reference LIKE :search1';
        $params['search1'] = $needle;
    }
}

$sortMap = [
    'newest' => 't.created_at DESC',
    'oldest' => 't.created_at ASC',
    'amount_desc' => 't.amount DESC',
    'amount_asc' => 't.amount ASC',
];
$sort = $sortMap[$_GET['sort'] ?? 'newest'] ?? $sortMap['newest'];

$whereSql = implode(' AND ', $where);

$select = $isOperator
    ? "SELECT t.id, t.type, t.method, t.amount, t.fee, t.net_amount, t.currency, t.status, t.reference, t.created_at, u.name AS user_name, u.email AS user_email
       FROM transactions t JOIN users u ON u.id = t.user_id WHERE {$whereSql} ORDER BY {$sort} LIMIT :limit OFFSET :offset"
    : "SELECT t.id, t.type, t.method, t.amount, t.fee, t.net_amount, t.currency, t.status, t.reference, t.created_at
       FROM transactions t WHERE {$whereSql} ORDER BY {$sort} LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($select);
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

$countSql = $isOperator
    ? "SELECT COUNT(*) FROM transactions t JOIN users u ON u.id = t.user_id WHERE {$whereSql}"
    : "SELECT COUNT(*) FROM transactions t WHERE {$whereSql}";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue(":{$key}", $value);
}
$countStmt->execute();
$total = (int) $countStmt->fetchColumn();

json_response(true, [
    'transactions' => $transactions,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => (int) ceil($total / $perPage),
    ],
], 'ok');
