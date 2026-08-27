<?php
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/functions.php';

$actor = api_guard(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$targetId = (int) ($input['user_id'] ?? 0);
$type = trim((string) ($input['document_type'] ?? ''));
$decision = trim((string) ($input['decision'] ?? ''));

$docTypes = kyc_document_types();

if ($targetId <= 0) {
    json_response(false, null, 'A customer is required.', 422);
}
if (!array_key_exists($type, $docTypes)) {
    json_response(false, null, 'Invalid document type.', 422);
}
if (!in_array($decision, ['verified', 'rejected'], true)) {
    json_response(false, null, 'Invalid decision.', 422);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, status FROM kyc_documents WHERE user_id = ? AND document_type = ?');
$stmt->execute([$targetId, $type]);
$doc = $stmt->fetch();

if (!$doc) {
    json_response(false, null, 'Document not found.', 404);
}
if ($doc['status'] === $decision) {
    json_response(false, null, 'This document already has that status.', 422);
}

$pdo->prepare('UPDATE kyc_documents SET status = ? WHERE id = ?')->execute([$decision, $doc['id']]);

$label = $docTypes[$type];
if ($decision === 'verified') {
    $title = 'Document verified';
    $message = "Your {$label} has been verified.";
} else {
    $title = 'Document rejected';
    $message = "Your {$label} was rejected. Please re-upload a valid document.";
}

$pdo->prepare('INSERT INTO notifications (user_id, type, title, message) VALUES (?, "kyc", ?, ?)')
    ->execute([$targetId, $title, $message]);

write_audit_log(
    (int) $actor['id'],
    $decision === 'verified' ? 'kyc_document_verified' : 'kyc_document_rejected',
    'kyc_document',
    $doc['id'],
    ['document_type' => $type, 'target_user_id' => $targetId]
);

json_response(true, ['status' => $decision], $decision === 'verified' ? 'Document verified.' : 'Document rejected.');