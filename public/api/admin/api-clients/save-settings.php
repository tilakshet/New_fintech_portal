<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$userId = (int) ($input['user_id'] ?? 0);

if ($userId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}

$payinUrl = trim((string) ($input['payin_callback_url'] ?? ''));
$payoutUrl = trim((string) ($input['payout_callback_url'] ?? ''));

foreach ([$payinUrl, $payoutUrl] as $url) {
    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
        json_response(false, null, 'Callback URLs must be valid, or left blank.', 422);
    }
}

$rawIps = (string) ($input['whitelisted_ips'] ?? '');
$ips = array_values(array_unique(array_filter(array_map('trim', explode("\n", $rawIps)))));

foreach ($ips as $ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        json_response(false, null, "\"{$ip}\" is not a valid IP address.", 422);
    }
}

$pdo = db();
$credStmt = $pdo->prepare('SELECT id FROM client_api_credentials WHERE user_id = ?');
$credStmt->execute([$userId]);
$credential = $credStmt->fetch();

if (!$credential) {
    json_response(false, null, 'Generate an API key for this customer first.', 422);
}

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE client_api_credentials SET payin_callback_url = ?, payout_callback_url = ? WHERE id = ?')
        ->execute([$payinUrl ?: null, $payoutUrl ?: null, $credential['id']]);

    // Whitelist is replaced wholesale rather than diffed — simpler and
    // safe, since this is a small admin-managed list, not high-frequency
    // data.
    $pdo->prepare('DELETE FROM client_whitelisted_ips WHERE credential_id = ?')->execute([$credential['id']]);

    if (!empty($ips)) {
        $insert = $pdo->prepare('INSERT INTO client_whitelisted_ips (credential_id, ip_address) VALUES (?, ?)');
        foreach ($ips as $ip) {
            $insert->execute([$credential['id'], $ip]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[api-clients/save-settings] ' . $e->getMessage());
    json_response(false, null, 'Unable to save these settings right now.', 500);
}

write_audit_log((int) $actor['id'], 'api_client_settings_updated', 'user', $userId, [
    'payin_callback_url' => $payinUrl ?: null,
    'payout_callback_url' => $payoutUrl ?: null,
    'whitelisted_ip_count' => count($ips),
]);

json_response(true, null, 'API settings saved.');