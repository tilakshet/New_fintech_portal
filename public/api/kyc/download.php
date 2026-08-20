<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$type = trim((string) ($_GET['document_type'] ?? ''));

if (!in_array($type, array_keys(kyc_document_types()), true)) {
    json_response(false, null, 'Invalid document type.', 422);
}

$stmt = db()->prepare(
    'SELECT original_filename, stored_filename, mime_type FROM kyc_documents WHERE user_id = ? AND document_type = ?'
);
$stmt->execute([$user['id'], $type]);
$doc = $stmt->fetch();

if (!$doc) {
    json_response(false, null, 'Document not found.', 404);
}

$path = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $doc['stored_filename'];

if (!is_file($path)) {
    json_response(false, null, 'Document not found on disk.', 404);
}

header('Content-Type: ' . $doc['mime_type']);
header('Content-Disposition: inline; filename="' . basename($doc['original_filename']) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
