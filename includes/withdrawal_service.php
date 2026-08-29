<?php
/**
 * Shared withdrawal logic, used by both the browser-session endpoint
 * (public/api/withdrawals/create.php) and the partner API endpoint
 * (public/api/partner/withdrawals/create.php). See deposit_service.php
 * for the return-array convention this follows.
 */

require_once __DIR__ . '/money.php';
require_once __DIR__ . '/gateway_selector.php';

function create_withdrawal(PDO $pdo, array $user, $rawAmount, $rawDestination, ?string $idempotencyKey = null): array
{
    if ($idempotencyKey !== null) {
        $existingStmt = $pdo->prepare(
            'SELECT reference, status, amount, fee, net_amount FROM transactions WHERE idempotency_key = ?'
        );
        $existingStmt->execute([$idempotencyKey]);
        $existing = $existingStmt->fetch();
        if ($existing) {
            return [
                'ok' => true,
                'status_code' => 200,
                'message' => 'Replayed: a withdrawal with this idempotency key already exists.',
                'data' => [
                    'reference' => $existing['reference'],
                    'status' => $existing['status'],
                    'amount' => $existing['amount'],
                    'fee' => $existing['fee'],
                    'net_amount' => $existing['net_amount'],
                    'replayed' => true,
                ],
            ];
        }
    }

    $amount = sanitize_amount($rawAmount);
    $destination = trim((string) ($rawDestination ?? ''));

    if ($amount === null || money_cmp($amount, '20.00') < 0) {
        return ['ok' => false, 'status_code' => 422, 'message' => 'Enter an amount of at least ₹20.00.', 'data' => null];
    }
    if ($destination === '' || mb_strlen($destination) > 190) {
        return ['ok' => false, 'status_code' => 422, 'message' => 'Select a valid withdrawal destination.', 'data' => null];
    }

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
            return [
                'ok' => false,
                'status_code' => 422,
                'message' => 'Insufficient available balance for this withdrawal plus the ' . money_format($fee) . ' fee.',
                'data' => ['available_balance' => $available],
            ];
        }

        $net = money_sub($amount, $fee);
        $reference = generate_reference('withdrawal');

        // Capacity is reserved against the net payout amount — Verapay's
        // fee is retained before the payout gateway ever sees the
        // transfer, so that's the figure that actually consumes the
        // gateway's daily limit.
        $selection = select_and_reserve_gateway($pdo, $net);
        if ($selection['gateway'] === null) {
            $pdo->rollBack();
            write_audit_log($user['id'], 'withdrawal_gateway_unavailable', 'transaction', null, ['amount' => $amount, 'net_amount' => $net, 'reason' => $selection['reason']]);
            return ['ok' => false, 'status_code' => 503, 'message' => 'Withdrawals are temporarily unavailable. Please try again shortly.', 'data' => null];
        }
        $gatewayId = (int) $selection['gateway']['id'];

        $insert = $pdo->prepare(
            'INSERT INTO transactions (user_id, type, method, amount, fee, net_amount, currency, status, reference, destination, gateway_id, idempotency_key)
             VALUES (?, "withdrawal", "Bank transfer", ?, ?, ?, "INR", "pending", ?, ?, ?, ?)'
        );
        $insert->execute([$user['id'], $amount, $fee, $net, $reference, $destination, $gatewayId, $idempotencyKey]);
        $txnId = (int) $pdo->lastInsertId();

        // Hold the funds: move out of available into pending until settled.
        $pdo->prepare('UPDATE wallets SET available_balance = available_balance - ?, pending_balance = pending_balance + ? WHERE user_id = ?')
            ->execute([$totalDeducted, $totalDeducted, $user['id']]);

        $pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "withdrawal", ?, ?)')
            ->execute([$user['id'], 'Withdrawal submitted', "Your withdrawal request {$reference} for " . money_format($amount) . ' is being processed.']);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[create_withdrawal] ' . $e->getMessage());
        return ['ok' => false, 'status_code' => 500, 'message' => 'Unable to process your withdrawal right now. Please try again.', 'data' => null];
    }

    write_audit_log($user['id'], 'withdrawal_created', 'transaction', $txnId, ['amount' => $amount, 'destination' => $destination, 'gateway_id' => $gatewayId]);

    return [
        'ok' => true,
        'status_code' => 200,
        'message' => 'Withdrawal submitted.',
        'data' => [
            'reference' => $reference,
            'status' => 'pending',
            'amount' => $amount,
            'fee' => $fee,
            'net_amount' => $net,
            'replayed' => false,
        ],
    ];
}