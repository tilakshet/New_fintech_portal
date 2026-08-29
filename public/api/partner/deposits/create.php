<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/partner_auth.php';
require_once __DIR__ . '/../../../../includes/deposit_service.php';

$user = partner_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$idempotencyKey = isset($input['idempotency_key']) ? trim((string) $input['idempotency_key']) : null;
if ($idempotencyKey === '') {
    $idempotencyKey = null;
}
if ($idempotencyKey === null) {
    json_response(false, null, 'idempotency_key is required for API-initiated deposits.', 422);
}

$result = create_deposit(db(), $user, $input['amount'] ?? null, $input['method'] ?? null, $idempotencyKey);

json_response($result['ok'], $result['data'], $result['message'], $result['status_code']);