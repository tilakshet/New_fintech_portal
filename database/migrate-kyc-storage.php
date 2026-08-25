<?php
/**
 * One-time migration: moves any KYC documents still sitting in the old flat
 * layout (storage/kyc-uploads/{user_id}_{type}_{random}.ext) into the new
 * per-customer layout (storage/kyc-uploads/{user_id}/{type}.ext) and
 * updates kyc_documents.stored_filename to match.
 *
 * Safe to re-run: rows already using the new layout (stored_filename
 * containing a "/") are skipped.
 *
 * Usage: php database/migrate-kyc-storage.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$rows = $pdo->query('SELECT id, user_id, document_type, stored_filename FROM kyc_documents')->fetchAll();

$migrated = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
    // Already in the new layout — nothing to do.
    if (str_contains($row['stored_filename'], '/')) {
        $skipped++;
        continue;
    }

    $oldPath = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $row['stored_filename'];
    if (!is_file($oldPath)) {
        echo "SKIP  (file missing on disk) user_id={$row['user_id']} type={$row['document_type']} stored_filename={$row['stored_filename']}\n";
        $errors++;
        continue;
    }

    $extension = pathinfo($row['stored_filename'], PATHINFO_EXTENSION);
    $newRelative = $row['user_id'] . '/' . $row['document_type'] . ($extension !== '' ? '.' . strtolower($extension) : '');
    $newPath = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $newRelative;

    $userDir = rtrim(KYC_UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $row['user_id'];
    if (!is_dir($userDir)) {
        mkdir($userDir, 0750, true);
    }

    if (!rename($oldPath, $newPath)) {
        echo "FAIL  could not move file for user_id={$row['user_id']} type={$row['document_type']}\n";
        $errors++;
        continue;
    }

    $pdo->prepare('UPDATE kyc_documents SET stored_filename = ? WHERE id = ?')->execute([$newRelative, $row['id']]);

    echo "MOVED user_id={$row['user_id']} type={$row['document_type']}: {$row['stored_filename']} -> {$newRelative}\n";
    $migrated++;
}

echo "\nDone. Migrated: {$migrated}, already up to date: {$skipped}, errors: {$errors}.\n";
