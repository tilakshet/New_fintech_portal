<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/money.php';
require_once __DIR__ . '/../../../includes/gateway_selector.php';
require_once __DIR__ . '/../../../includes/gateway_webhooks.php';
require_once __DIR__ . '/../../../includes/gateway_providers/dispatch.php';

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
$reference = generate_reference('deposit');

$pdo = db();
$pdo->beginTransaction();
try {
    // Capacity is reserved against the gross amount the customer is
    // charged — that's what actually flows through the pay-in gateway,
    // before Verapay's fee is taken out of it.
    $selection = select_and_reserve_gateway($pdo, $amount);
    if ($selection['gateway'] === null) {
        $pdo->rollBack();
        write_audit_log($user['id'], 'deposit_gateway_unavailable', 'transaction', null, ['amount' => $amount, 'method' => $method, 'reason' => $selection['reason']]);
        json_response(false, null, 'Deposits are temporarily unavailable. Please try again shortly.', 503);
    }
    $gateway = $selection['gateway'];
    $gatewayId = (int) $gateway['id'];

    // A gateway with a real, live-integrated provider settles for real via
    // checkout + webhook, so it can never be synchronously "success"
    // regardless of method — that simulated instant-success path only
    // still applies to gateways nothing has actually been wired up to yet.
    $liveGatewayConfigured = gateway_supports_live_order_creation($gateway);
    $status = (!$liveGatewayConfigured && $method === 'Debit card') ? 'success' : 'pending';

    $insert = $pdo->prepare(
        'INSERT INTO transactions (user_id, type, method, amount, fee, net_amount, currency, status, reference, destination, gateway_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$user['id'], 'deposit', $method, $amount, $fee, $net, 'INR', $status, $reference, $method, $gatewayId]);
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

write_audit_log($user['id'], 'deposit_created', 'transaction', $txnId, ['amount' => $amount, 'method' => $method, 'status' => $status, 'gateway_id' => $gatewayId]);

// The outbound call to the provider happens only now, after the DB
// transaction has committed — never make a network call while holding the
// wallet/usage row locks above.
$checkout = null;
$message = $status === 'success' ? 'Deposit completed.' : 'Deposit submitted and pending settlement.';

if ($liveGatewayConfigured) {
    $customerPhone = null;
    $phoneStmt = $pdo->prepare('SELECT mobile_number FROM business_profiles WHERE user_id = ?');
    $phoneStmt->execute([$user['id']]);
    $customerPhone = $phoneStmt->fetchColumn() ?: null;

    try {
        $orderResult = create_gateway_order($gateway, $reference, $amount, $user, $customerPhone);
        $pdo->prepare('UPDATE transactions SET gateway_txn_id = ? WHERE id = ?')->execute([$orderResult['gateway_txn_id'], $txnId]);
        $checkout = $orderResult['checkout'];
        $message = 'Complete your payment to finish this deposit.';
    } catch (GatewayOrderAmbiguousException $e) {
        // We do not know if the provider actually created the order —
        // never auto-retry on a different gateway here. The transaction
        // stays pending with no order id; only a webhook (or manual admin
        // reconciliation) can resolve it from here.
        error_log('[deposits/create] gateway order ambiguous: ' . $e->getMessage());
        write_audit_log($user['id'], 'deposit_gateway_order_ambiguous', 'transaction', $txnId, ['gateway_id' => $gatewayId, 'provider' => $gateway['provider'], 'reason' => $e->getMessage()]);
        $message = 'Deposit submitted, but we could not confirm the payment gateway accepted it yet. This will update automatically once confirmed — contact support if a payment was taken and this does not resolve.';
    } catch (Throwable $e) {
        // A definite, synchronous rejection — unlike the ambiguous case
        // above, we know for certain no order was created, so it's safe
        // to unwind the reservation and mark this attempt failed.
        //
        // GatewayCustomerActionRequiredException's message is written to be
        // safe and useful shown verbatim (e.g. "add a phone number"); any
        // other failure's message is an opaque provider rejection reason
        // (e.g. "Authentication failed") that must NOT reach the customer —
        // it means something is misconfigured on our side, not theirs.
        $isCustomerActionable = $e instanceof GatewayCustomerActionRequiredException;

        error_log('[deposits/create] gateway order failed: ' . $e->getMessage());
        write_audit_log($user['id'], 'deposit_gateway_order_failed', 'transaction', $txnId, ['gateway_id' => $gatewayId, 'provider' => $gateway['provider'], 'reason' => $e->getMessage()]);

        $pdo->beginTransaction();
        try {
            $txnLock = $pdo->prepare(
                'SELECT id, user_id, type, status, amount, fee, net_amount, gateway_id
                 FROM transactions WHERE id = ? FOR UPDATE'
            );
            $txnLock->execute([$txnId]);
            $txnRow = $txnLock->fetch();
            if ($txnRow && $txnRow['status'] === 'pending') {
                apply_transaction_outcome($pdo, $txnRow, 'failed', null);
                release_gateway_reservation($pdo, $gatewayId, $amount);
            }
            $pdo->commit();
        } catch (Throwable $e2) {
            $pdo->rollBack();
            error_log('[deposits/create] failed to unwind gateway order failure: ' . $e2->getMessage());
        }

        $publicMessage = $isCustomerActionable
            ? $e->getMessage()
            : 'This deposit could not be started — the payment gateway rejected the request. Please try again.';
        json_response(false, ['reference' => $reference], $publicMessage, $isCustomerActionable ? 422 : 502);
    }
}

json_response(true, [
    'reference' => $reference,
    'status' => $status,
    'amount' => $amount,
    'fee' => $fee,
    'net_amount' => $net,
    'method' => $method,
    'checkout' => $checkout,
], $message);
