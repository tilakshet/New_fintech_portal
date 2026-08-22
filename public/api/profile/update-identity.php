<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$existing = db()->prepare('SELECT kyc_locked FROM merchant_profiles WHERE user_id = ?');
$existing->execute([$user['id']]);
$existingRow = $existing->fetch();

if ($existingRow && (int) $existingRow['kyc_locked'] === 1) {
    json_response(false, null, 'Your business and KYC details are already locked and cannot be edited.', 409);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$legalCompanyName = trim((string) ($input['legal_company_name'] ?? ''));
$companyType = trim((string) ($input['company_type'] ?? ''));
$mobileNumber = trim((string) ($input['mobile_number'] ?? ''));
$whatsappNumber = trim((string) ($input['whatsapp_number'] ?? ''));
$panNumber = trim((string) ($input['pan_number'] ?? ''));
$gstin = trim((string) ($input['gstin'] ?? ''));
$aadharNumber = trim((string) ($input['aadhar_number'] ?? ''));
$officeAddress = trim((string) ($input['office_address'] ?? ''));

$accountHolder = trim((string) ($input['account_holder'] ?? ''));
$accountNumber = trim((string) ($input['account_number'] ?? ''));
$ifscCode = trim((string) ($input['ifsc_code'] ?? ''));
$bankName = trim((string) ($input['bank_name'] ?? ''));

$errors = [];

if ($legalCompanyName === '' || mb_strlen($legalCompanyName) > 160) {
    $errors[] = 'Enter a valid legal company name.';
}
if ($companyType === '' || mb_strlen($companyType) > 60) {
    $errors[] = 'Select a company type.';
}
if ($mobileNumber === '' || !preg_match('/^[0-9+\-\s]{6,20}$/', $mobileNumber)) {
    $errors[] = 'Enter a valid mobile number.';
}
if ($whatsappNumber !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $whatsappNumber)) {
    $errors[] = 'Enter a valid WhatsApp number.';
}
if ($panNumber === '' || !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper($panNumber))) {
    $errors[] = 'Enter a valid PAN number (e.g. ABCDE1234F).';
}
if ($gstin !== '' && !preg_match('/^[0-9A-Z]{15}$/', strtoupper($gstin))) {
    $errors[] = 'Enter a valid 15-character GSTIN.';
}
if ($aadharNumber === '' || !preg_match('/^\d{12}$/', $aadharNumber)) {
    $errors[] = 'Enter a valid 12-digit Aadhar number.';
}
if ($officeAddress === '' || mb_strlen($officeAddress) > 255) {
    $errors[] = 'Enter a valid office address (up to 255 characters).';
}
if ($accountHolder === '' || mb_strlen($accountHolder) > 120) {
    $errors[] = 'Enter a valid account holder name.';
}
if ($accountNumber === '' || !preg_match('/^[0-9]{6,40}$/', $accountNumber)) {
    $errors[] = 'Enter a valid account number.';
}
if ($ifscCode === '' || !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($ifscCode))) {
    $errors[] = 'Enter a valid IFSC code (e.g. HDFC0001234).';
}
if ($bankName === '' || mb_strlen($bankName) > 120) {
    $errors[] = 'Enter a valid bank name.';
}

if ($errors) {
    json_response(false, ['errors' => $errors], $errors[0], 422);
}

$panNumber = strtoupper($panNumber);
$gstin = $gstin !== '' ? strtoupper($gstin) : null;
$ifscCode = strtoupper($ifscCode);
$whatsappNumber = $whatsappNumber !== '' ? $whatsappNumber : null;

$pdo = db();
$pdo->beginTransaction();

try {
    $pdo->prepare(
        'INSERT INTO merchant_profiles
            (user_id, legal_company_name, company_type, mobile_number, whatsapp_number, pan_number, gstin, aadhar_number, office_address, kyc_locked)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
            legal_company_name = VALUES(legal_company_name),
            company_type = VALUES(company_type),
            mobile_number = VALUES(mobile_number),
            whatsapp_number = VALUES(whatsapp_number),
            pan_number = VALUES(pan_number),
            gstin = VALUES(gstin),
            aadhar_number = VALUES(aadhar_number),
            office_address = VALUES(office_address),
            kyc_locked = 1'
    )->execute([
        $user['id'], $legalCompanyName, $companyType, $mobileNumber,
        $whatsappNumber, $panNumber, $gstin, $aadharNumber, $officeAddress,
    ]);

    $pdo->prepare(
        'INSERT INTO settlement_banks (user_id, account_holder, account_number, ifsc_code, bank_name)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            account_holder = VALUES(account_holder),
            account_number = VALUES(account_number),
            ifsc_code = VALUES(ifsc_code),
            bank_name = VALUES(bank_name)'
    )->execute([$user['id'], $accountHolder, $accountNumber, $ifscCode, $bankName]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(false, null, 'Something went wrong while saving your details. Please try again.', 500);
}

write_audit_log((int) $user['id'], 'kyc_profile_locked', 'merchant_profile', (int) $user['id'], []);

json_response(true, null, 'Your business and KYC details have been saved and locked.');
