<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/gateway_selector.php';

$user = require_auth();
require_role($user, 'admin');

$pdo = db();
$stmt = $pdo->query(
    'SELECT id, display_name, provider, api_key_last4, public_key, status, is_default, priority, daily_limit_amount,
            (webhook_secret_encrypted IS NOT NULL) AS webhook_configured,
            (api_key_encrypted IS NOT NULL) AS has_live_secret, created_at, updated_at
     FROM payment_gateways ORDER BY is_default DESC, priority ASC, created_at ASC'
);
$gateways = $stmt->fetchAll();

foreach ($gateways as &$gateway) {
    $usage = gateway_daily_usage_snapshot($pdo, (int) $gateway['id']);
    $gateway['used_today'] = $usage['used_amount'];
    $gateway['transaction_count_today'] = $usage['transaction_count'];
    $gateway['remaining_today'] = $gateway['daily_limit_amount'] === null
        ? null
        : money_sub($gateway['daily_limit_amount'], $usage['used_amount']);
    $gateway['webhook_configured'] = (bool) $gateway['webhook_configured'];
    $gateway['live_integration'] = $gateway['provider'] === 'razorpay' && (bool) $gateway['has_live_secret'] && $gateway['public_key'];
    $webhookPath = $gateway['provider'] === 'razorpay' ? 'razorpay.php' : 'gateway.php';
    $gateway['webhook_url'] = APP_URL . "/api/webhooks/{$webhookPath}?gateway_id={$gateway['id']}";
    unset($gateway['has_live_secret']);
}
unset($gateway);

json_response(true, ['gateways' => $gateways], 'ok');
