<?php
/**
 * Business & KYC profile — GET returns the current record with sensitive
 * identifiers masked (last 4 digits only, mirroring the payment-gateway
 * API key pattern). POST upserts it; identity/bank numbers are hashed on
 * save and never returned in full again.
 */
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = require_auth();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT legal_company_name, company_type, mobile_number, whatsapp_number, pan_number, gstin,
                office_address, identity_last4, bank_account_holder, bank_account_last4, bank_ifsc, updated_at
         FROM business_profiles WHERE user_id = ?'
    );
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch() ?: null;

    json_response(true, ['profile' => $profile], 'ok');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_guard();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // The Business/KYC and Settlement Bank forms each submit only their own
    // fields, so this is a partial update: a field merges over the existing
    // row only when the request actually included that key. A key that's
    // present but empty (e.g. clearing office_address) is still honored as
    // "set to blank" — it's *absent* keys that mean "leave untouched".
    $plainFields = [
        'legal_company_name' => null,
        'company_type' => null,
        'mobile_number' => null,
        'whatsapp_number' => null,
        'pan_number' => 'upper',
        'gstin' => 'upper',
        'office_address' => null,
        'bank_account_holder' => null,
        'bank_ifsc' => 'upper',
    ];

    $existing = $pdo->prepare('SELECT * FROM business_profiles WHERE user_id = ?');
    $existing->execute([$user['id']]);
    $current = $existing->fetch() ?: array_fill_keys(array_keys($plainFields), null);

    $values = [];
    foreach ($plainFields as $field => $transform) {
        if (array_key_exists($field, $input)) {
            $value = trim((string) $input[$field]);
            if ($transform === 'upper') {
                $value = strtoupper($value);
            }
            $values[$field] = $value === '' ? null : $value;
        } else {
            $values[$field] = $current[$field] ?? null;
        }
    }

    if ($values['pan_number'] !== null && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $values['pan_number'])) {
        json_response(false, null, 'Enter a valid PAN number (e.g. AAAAA0000A).', 422);
    }
    if ($values['gstin'] !== null && !preg_match('/^[0-9]{2}[A-Z0-9]{13}$/', $values['gstin'])) {
        json_response(false, null, 'Enter a valid 15-character GSTIN.', 422);
    }
    if ($values['bank_ifsc'] !== null && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $values['bank_ifsc'])) {
        json_response(false, null, 'Enter a valid 11-character IFSC code.', 422);
    }

    // Identity/bank numbers: only present when the user typed a new one
    // (an absent or blank field means "keep what's on file" — see loadBusinessProfile()
    // in profile.js, which never re-populates these from the masked GET response).
    $identityLast4 = $current['identity_last4'] ?? null;
    $identityHash = $current['identity_hash'] ?? null;
    if (array_key_exists('identity_number', $input)) {
        $identityNumber = preg_replace('/\s+/', '', (string) $input['identity_number']);
        if ($identityNumber !== '') {
            if (!preg_match('/^\d{12}$/', $identityNumber)) {
                json_response(false, null, 'Enter a valid 12-digit identity document number.', 422);
            }
            $identityLast4 = mb_substr($identityNumber, -4);
            $identityHash = password_hash($identityNumber, PASSWORD_DEFAULT);
        }
    }

    $bankLast4 = $current['bank_account_last4'] ?? null;
    $bankHash = $current['bank_account_hash'] ?? null;
    if (array_key_exists('bank_account_number', $input)) {
        $bankAccountNumber = preg_replace('/\s+/', '', (string) $input['bank_account_number']);
        if ($bankAccountNumber !== '') {
            $bankLast4 = mb_substr($bankAccountNumber, -4);
            $bankHash = password_hash($bankAccountNumber, PASSWORD_DEFAULT);
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO business_profiles
                (user_id, legal_company_name, company_type, mobile_number, whatsapp_number, pan_number, gstin,
                 office_address, identity_last4, identity_hash, bank_account_holder, bank_account_last4, bank_account_hash, bank_ifsc)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                legal_company_name = VALUES(legal_company_name),
                company_type = VALUES(company_type),
                mobile_number = VALUES(mobile_number),
                whatsapp_number = VALUES(whatsapp_number),
                pan_number = VALUES(pan_number),
                gstin = VALUES(gstin),
                office_address = VALUES(office_address),
                identity_last4 = VALUES(identity_last4),
                identity_hash = VALUES(identity_hash),
                bank_account_holder = VALUES(bank_account_holder),
                bank_account_last4 = VALUES(bank_account_last4),
                bank_account_hash = VALUES(bank_account_hash),
                bank_ifsc = VALUES(bank_ifsc)'
        )->execute([
            $user['id'], $values['legal_company_name'], $values['company_type'], $values['mobile_number'], $values['whatsapp_number'],
            $values['pan_number'], $values['gstin'], $values['office_address'], $identityLast4, $identityHash,
            $values['bank_account_holder'], $bankLast4, $bankHash, $values['bank_ifsc'],
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[profile/business] ' . $e->getMessage());
        json_response(false, null, 'Unable to save your business information. Please try again.', 500);
    }

    write_audit_log((int) $user['id'], 'business_profile_updated', 'user', (int) $user['id'], []);

    json_response(true, null, 'Business information saved.');
}

json_response(false, null, 'Method not allowed.', 405);
