<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed.', 405);
}

$targetId = (int) ($_GET['user_id'] ?? 0);
if ($targetId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}

$pdo = db();
$customerStmt = $pdo->prepare("SELECT id, name, email, avatar_initials, gender FROM users WHERE id = ? AND role = 'customer'");
$customerStmt->execute([$targetId]);
$customer = $customerStmt->fetch();

if (!$customer) {
    json_response(false, null, 'Customer not found.', 404);
}

$stmt = $pdo->prepare(
    'SELECT document_type, original_filename, status, file_size, uploaded_at FROM kyc_documents WHERE user_id = ?'
);
$stmt->execute([$targetId]);

$byType = [];
foreach ($stmt->fetchAll() as $row) {
    $byType[$row['document_type']] = [
        'original_filename' => $row['original_filename'],
        'status' => $row['status'],
        'file_size' => (int) $row['file_size'],
        'uploaded_at' => $row['uploaded_at'],
    ];
}

$documents = [];
foreach (kyc_document_types() as $type => $label) {
    $documents[] = [
        'type' => $type,
        'label' => $label,
        'uploaded' => isset($byType[$type]),
    ] + ($byType[$type] ?? ['original_filename' => null, 'status' => null, 'file_size' => null, 'uploaded_at' => null]);
}

json_response(true, [
    'customer' => $customer,
    'documents' => $documents,
], 'ok');