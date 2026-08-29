<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard(['customer']);

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
    'UPDATE customer_api_credentials SET payout_callback_url = ?, payin_callback_url = ? WHERE user_id = ?'
);
$stmt->execute([$payoutUrl ?: null, $payinUrl ?: null, $user['id']]);

if ($stmt->rowCount() === 0) {
    $exists = $pdo->prepare('SELECT 1 FROM customer_api_credentials WHERE user_id = ?');
    $exists->execute([$user['id']]);
    if (!$exists->fetchColumn()) {
        json_response(false, null, 'API credentials have not been provisioned yet. Reload the page and try again.', 409);
    }
}

write_audit_log((int) $user['id'], 'api_webhook_config_updated', 'user', (int) $user['id'], []);

json_response(true, null, 'Webhook configuration saved.');
