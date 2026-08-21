<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$user = api_guard();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$stmt = db()->prepare(
    'SELECT document_type, original_filename, status, file_size, uploaded_at FROM kyc_documents WHERE user_id = ?'
);
$stmt->execute([$user['id']]);

$documents = [];
foreach ($stmt->fetchAll() as $row) {
       $documents[$row['document_type']] = [
        'original_filename' => $row['original_filename'],
        'status' => $row['status'],
        'file_size' => (int) $row['file_size'],
        'uploaded_at' => $row['uploaded_at'],
    ];
}

json_response(true, ['documents' => $documents]);
