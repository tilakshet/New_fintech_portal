<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$user = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$payoutUrl = trim((string) ($input['payout_callback_url'] ?? ''));
$payinUrl = trim((string) ($input['payin_callback_url'] ?? ''));

$validateUrl = static function (string $url, string $label): void {
    if ($url === '') {
        return;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
        json_response(false, null, "Enter a valid https:// URL for {$label}.", 422);
    }
};

$validateUrl($payoutUrl, 'the payout callback URL');
$validateUrl($payinUrl, 'the pay-in callback URL');

$pdo = db();
$stmt = $pdo->prepare(
    'UPDATE platform_api_settings SET payout_callback_url = ?, payin_callback_url = ? WHERE id = 1'
);
$stmt->execute([$payoutUrl ?: null, $payinUrl ?: null]);

$exists = $pdo->query('SELECT 1 FROM platform_api_settings WHERE id = 1')->fetchColumn();
if (!$exists) {
    json_response(false, null, 'API credentials have not been provisioned yet. Reload the page and try again.', 409);
}

write_audit_log((int) $user['id'], 'platform_webhook_config_updated', 'platform_api_settings', 1, []);

json_response(true, null, 'Webhook configuration saved.');
