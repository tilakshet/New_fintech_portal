<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = require_auth();
require_role($user, 'customer');

$pdo = db();

$walletStmt = $pdo->prepare('SELECT available_balance, pending_balance, currency, updated_at FROM wallets WHERE user_id = ?');
$walletStmt->execute([$user['id']]);
$wallet = $walletStmt->fetch() ?: ['available_balance' => '0.00', 'pending_balance' => '0.00', 'currency' => 'INR', 'updated_at' => null];

$activityStmt = $pdo->prepare(
    'SELECT id, type, method, amount, fee, net_amount, currency, status, reference, created_at
     FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
);
$activityStmt->execute([$user['id']]);

json_response(true, ['wallet' => $wallet, 'activity' => $activityStmt->fetchAll()], 'ok');
