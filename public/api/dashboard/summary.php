<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = require_auth();
$pdo = db();
$isOperator = in_array($user['role'], ['admin', 'operator'], true);

$data = [];

if (!$isOperator) {
    $walletStmt = $pdo->prepare('SELECT available_balance, pending_balance, currency FROM wallets WHERE user_id = ?');
    $walletStmt->execute([$user['id']]);
    $wallet = $walletStmt->fetch() ?: ['available_balance' => '0.00', 'pending_balance' => '0.00', 'currency' => 'INR'];
    $data['wallet'] = $wallet;

    $scopeSql = 'WHERE user_id = :uid';
    $params = ['uid' => $user['id']];
} else {
    $scopeSql = 'WHERE 1=1';
    $params = [];
}

$today = date('Y-m-d');
$todayStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions {$scopeSql} AND DATE(created_at) = :today");
$todayStmt->execute($params + ['today' => $today]);
$data['today_count'] = (int) $todayStmt->fetchColumn();

/** Total + per-status count/amount breakdown for one transaction type — backs the Deposits/Withdrawals report cards. */
function type_report(PDO $pdo, string $scopeSql, array $params, string $type): array
{
    $stmt = $pdo->prepare("SELECT status, COUNT(*) c, COALESCE(SUM(amount), 0) s FROM transactions {$scopeSql} AND type = :type GROUP BY status");
    $stmt->execute($params + ['type' => $type]);

    $report = [
        'total' => ['count' => 0, 'amount' => '0.00'],
        'success' => ['count' => 0, 'amount' => '0.00'],
        'pending' => ['count' => 0, 'amount' => '0.00'],
        'failed' => ['count' => 0, 'amount' => '0.00'],
    ];
    foreach ($stmt->fetchAll() as $row) {
        $report['total']['count'] += (int) $row['c'];
        $report['total']['amount'] = bcadd($report['total']['amount'], (string) $row['s'], 2);
        if (isset($report[$row['status']])) {
            $report[$row['status']] = ['count' => (int) $row['c'], 'amount' => (string) $row['s']];
        }
    }
    return $report;
}

$data['deposits_report'] = type_report($pdo, $scopeSql, $params, 'deposit');
$data['withdrawals_report'] = type_report($pdo, $scopeSql, $params, 'withdrawal');

/** Successful daily amount for one type, last 7 days — backs the two trend charts. */
function daily_trend(PDO $pdo, string $scopeSql, array $params, string $type): array
{
    $points = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions {$scopeSql} AND type = :type AND status = 'success' AND DATE(created_at) = :day");
        $stmt->execute($params + ['type' => $type, 'day' => $day]);
        $points[] = ['label' => date('D', strtotime($day)), 'value' => (float) $stmt->fetchColumn()];
    }
    return $points;
}

$data['deposits_trend'] = daily_trend($pdo, $scopeSql, $params, 'deposit');
$data['withdrawals_trend'] = daily_trend($pdo, $scopeSql, $params, 'withdrawal');

$recentSql = $isOperator
    ? "SELECT t.id, t.type, t.amount, t.currency, t.status, t.reference, t.created_at, u.name AS user_name
       FROM transactions t JOIN users u ON u.id = t.user_id
       ORDER BY t.created_at DESC LIMIT 6"
    : "SELECT id, type, amount, currency, status, reference, created_at FROM transactions WHERE user_id = :uid ORDER BY created_at DESC LIMIT 6";
$recentStmt = $pdo->prepare($recentSql);
$recentStmt->execute($isOperator ? [] : ['uid' => $user['id']]);
$data['recent'] = $recentStmt->fetchAll();

json_response(true, $data, 'ok');
