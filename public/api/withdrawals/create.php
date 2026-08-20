<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/money.php';

$user = api_guard(['customer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$amount = sanitize_amount($input['amount'] ?? null);
$destination = trim((string) ($input['destination'] ?? ''));

if ($amount === null || money_cmp($amount, '20.00') < 0) {
    json_response(false, null, 'Enter an amount of at least ₹20.00.', 422);
}
if ($destination === '' || mb_strlen($destination) > 190) {
    json_response(false, null, 'Select a valid withdrawal destination.', 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $walletStmt = $pdo->prepare('SELECT available_balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $walletStmt->execute([$user['id']]);
    $wallet = $walletStmt->fetch();
    $available = $wallet['available_balance'] ?? '0.00';

    $fee = calculate_fee('withdrawal', 'Bank transfer', $amount);
    $totalDeducted = money_add($amount, $fee);

    if (money_cmp($totalDeducted, $available) > 0) {
        $pdo->rollBack();
        json_response(false, ['available_balance' => $available], 'Insufficient available balance for this withdrawal plus the ' . money_format($fee) . ' fee.', 422);
    }

    $net = money_sub($amount, $fee);
    $reference = generate_reference('withdrawal');

    $insert = $pdo->prepare(
        'INSERT INTO transactions (user_id, type, method, amount, fee, net_amount, currency, status, reference, destination)
         VALUES (?, "withdrawal", "Bank transfer", ?, ?, ?, "INR", "pending", ?, ?)'
    );
    $insert->execute([$user['id'], $amount, $fee, $net, $reference, $destination]);
    $txnId = (int) $pdo->lastInsertId();

    // Hold the funds: move out of available into pending until settled.
    $pdo->prepare('UPDATE wallets SET available_balance = available_balance - ?, pending_balance = pending_balance + ? WHERE user_id = ?')
        ->execute([$totalDeducted, $totalDeducted, $user['id']]);

    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "withdrawal", ?, ?)')
        ->execute([$user['id'], 'Withdrawal submitted', "Your withdrawal request {$reference} for " . money_format($amount) . ' is being processed.']);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[withdrawals/create] ' . $e->getMessage());
    json_response(false, null, 'Unable to process your withdrawal right now. Please try again.', 500);
}

write_audit_log($user['id'], 'withdrawal_created', 'transaction', $txnId, ['amount' => $amount, 'destination' => $destination]);

json_response(true, [
    'reference' => $reference,
    'status' => 'pending',
    'amount' => $amount,
    'fee' => $fee,
    'net_amount' => $net,
], 'Withdrawal submitted.');
