<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$allowedProviders = ['razorpay', 'payu', 'cashfree', 'stripe', 'paypal', 'other'];

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$name = trim((string) ($input['display_name'] ?? ''));
$provider = $input['provider'] ?? '';
$apiKey = trim((string) ($input['api_key'] ?? ''));

if ($name === '' || mb_strlen($name) > 80) {
    json_response(false, null, 'Enter a name for this gateway (up to 80 characters).', 422);
}
if (!in_array($provider, $allowedProviders, true)) {
    json_response(false, null, 'Select a valid provider.', 422);
}
if (mb_strlen($apiKey) < 8) {
    json_response(false, null, 'Enter an API key of at least 8 characters.', 422);
}

$pdo = db();
$last4 = mb_substr($apiKey, -4);
$hash = password_hash($apiKey, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO payment_gateways (display_name, provider, api_key_last4, api_key_hash, status, is_default) VALUES (?, ?, ?, ?, "inactive", 0)'
);
$stmt->execute([$name, $provider, $last4, $hash]);
$id = (int) $pdo->lastInsertId();

write_audit_log((int) $actor['id'], 'gateway_created', 'payment_gateway', $id, ['provider' => $provider, 'display_name' => $name]);

json_response(true, ['id' => $id], 'Gateway added. Activate it when you are ready to accept traffic through it.', 201);
