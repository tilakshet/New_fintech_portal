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
$method = in_array($input['method'] ?? '', ['Bank transfer', 'Debit card'], true) ? $input['method'] : null;

if ($amount === null || money_cmp($amount, '10.00') < 0) {
    json_response(false, null, 'Enter an amount of at least ₹10.00.', 422);
}
if (money_cmp($amount, '50000.00') > 0) {
    json_response(false, null, 'Deposits are limited to ₹50,000.00 per transaction.', 422);
}
if ($method === null) {
    json_response(false, null, 'Select a valid payment method.', 422);
}

$fee = calculate_fee('deposit', $method, $amount);
$net = money_sub($amount, $fee);
$status = $method === 'Debit card' ? 'success' : 'pending';
$reference = generate_reference('deposit');

$pdo = db();
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        'INSERT INTO transactions (user_id, type, method, amount, fee, net_amount, currency, status, reference, destination)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$user['id'], 'deposit', $method, $amount, $fee, $net, 'INR', $status, $reference, $method]);
    $txnId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT IGNORE INTO wallets (user_id, available_balance, pending_balance, currency) VALUES (?, 0.00, 0.00, "INR")')->execute([$user['id']]);

    $column = $status === 'success' ? 'available_balance' : 'pending_balance';
    $pdo->prepare("UPDATE wallets SET {$column} = {$column} + ? WHERE user_id = ?")->execute([$net, $user['id']]);

    $notifTitle = $status === 'success' ? 'Deposit received' : 'Deposit pending';
    $notifMessage = $status === 'success'
        ? "Your deposit of " . money_format($amount, 'INR') . " ({$reference}) was completed successfully."
        : "Your deposit of " . money_format($amount, 'INR') . " ({$reference}) is being processed and typically settles in 1-2 business days.";
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "deposit", ?, ?)')
        ->execute([$user['id'], $notifTitle, $notifMessage]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[deposits/create] ' . $e->getMessage());
    json_response(false, null, 'Unable to process your deposit right now. Please try again.', 500);
}

write_audit_log($user['id'], 'deposit_created', 'transaction', $txnId, ['amount' => $amount, 'method' => $method, 'status' => $status]);

json_response(true, [
    'reference' => $reference,
    'status' => $status,
    'amount' => $amount,
    'fee' => $fee,
    'net_amount' => $net,
    'method' => $method,
], $status === 'success' ? 'Deposit completed.' : 'Deposit submitted and pending settlement.');
