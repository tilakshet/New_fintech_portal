<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$type = trim((string) ($_POST['document_type'] ?? ''));
$validTypes = array_keys(kyc_document_types());

if (!in_array($type, $validTypes, true)) {
    json_response(false, null, 'Invalid document type.', 422);
}

if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    json_response(false, null, 'Please choose a file to upload.', 422);
}

$file = $_FILES['document'];

if ($file['size'] > KYC_UPLOAD_MAX_BYTES) {
    json_response(false, null, 'File is too large.', 422);
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = is_array(KYC_UPLOAD_ALLOWED_MIME) ? KYC_UPLOAD_ALLOWED_MIME : explode(',', KYC_UPLOAD_ALLOWED_MIME);

if (!in_array($mimeType, $allowedMimes, true)) {
    json_response(false, null, 'Unsupported file type.', 422);
}

// Each customer gets their own subfolder (storage/kyc-uploads/{user_id}/),
// with one file per document type inside it — e.g.
// storage/kyc-uploads/3/aadhar_card.pdf — instead of every customer's
// documents being dumped flat into one folder with a random suffix. A
// re-upload of the same document type overwrites the file in place, which
// matches the one-row-per-user-per-type semantics already enforced by the
// kyc_documents table's unique key.
$userDir = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $user['id'];
if (!is_dir($userDir)) {
    mkdir($userDir, 0750, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$storedFilename = $user['id'] . '/' . $type . ($extension !== '' ? '.' . strtolower($extension) : '');
$destination = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $storedFilename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_response(false, null, 'Failed to save the uploaded file. Please try again.', 500);
}

$existingStmt = db()->prepare('SELECT stored_filename FROM kyc_documents WHERE user_id = ? AND document_type = ?');
$existingStmt->execute([$user['id'], $type]);
$existing = $existingStmt->fetch();

db()->prepare(
    'INSERT INTO kyc_documents (user_id, document_type, original_filename, stored_filename, mime_type, file_size, status)
     VALUES (?, ?, ?, ?, ?, ?, "pending")
     ON DUPLICATE KEY UPDATE
        original_filename = VALUES(original_filename),
        stored_filename = VALUES(stored_filename),
        mime_type = VALUES(mime_type),
        file_size = VALUES(file_size),
        status = "pending",
        uploaded_at = CURRENT_TIMESTAMP'
)->execute([$user['id'], $type, $file['name'], $storedFilename, $mimeType, $file['size']]);

if ($existing && $existing['stored_filename'] !== $storedFilename) {
    $oldPath = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $existing['stored_filename'];
    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

write_audit_log((int) $user['id'], 'kyc_document_uploaded', 'kyc_document', (int) $user['id'], ['document_type' => $type]);

json_response(true, [
    'type' => $type,
    'original_filename' => $file['name'],
    'status' => 'pending',
], 'Document uploaded.');
