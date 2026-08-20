<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$profileStmt = db()->prepare(
    'SELECT legal_company_name, company_type, mobile_number, whatsapp_number, pan_number, gstin, aadhar_number, office_address, kyc_locked
     FROM merchant_profiles WHERE user_id = ?'
);
$profileStmt->execute([$user['id']]);
$profile = $profileStmt->fetch();

$bankStmt = db()->prepare(
    'SELECT account_holder, account_number, ifsc_code, bank_name FROM settlement_banks WHERE user_id = ?'
);
$bankStmt->execute([$user['id']]);
$bank = $bankStmt->fetch();

json_response(true, [
    'account' => [
        'name' => $user['name'] ?? null,
        'email' => $user['email'] ?? null,
    ],
    'profile' => $profile ?: null,
    'bank' => $bank ?: null,
    'kyc_locked' => (bool) ($profile['kyc_locked'] ?? false),
]);
