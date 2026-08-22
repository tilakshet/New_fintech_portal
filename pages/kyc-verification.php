<?php
require_once __DIR__ . '/../includes/banner.php';
$extraScripts = ['/assets/js/pages/kyc-verification.js'];
$docTypes = kyc_document_types();

render_hero_banner(
    $user,
    'KYC Verification',
    'Securely upload and verify your business identity.',
    [
        ['id' => 'hero-account-id', 'label' => 'Account ID #' . (int) $user['id'], 'tone' => 'neutral'],
    ]
);
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <?php foreach ($docTypes as $type => $label): ?>
    <div class="card" data-doc-card="<?= e($type) ?>">
        <div class="flex items-start justify-between gap-3 mb-3">
            <h2 class="card-title"><?= e($label) ?></h2>
            <span data-doc-badge class="badge-neutral hidden"></span>
        </div>
        <p data-doc-filename class="text-sm text-text-secondary mb-3 truncate hidden">
            <span data-doc-filename-text></span>
            &middot; <a data-doc-view href="#" target="_blank" rel="noopener" class="text-brand-emphasis hover:underline">View</a>
        </p>
        <form data-doc-form data-doc-type="<?= e($type) ?>" novalidate>
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="file" data-doc-input accept=".pdf,.jpg,.jpeg,.png" class="field-input flex-1" aria-label="Choose <?= e($label) ?> file">
                <button type="submit" class="btn-secondary shrink-0" data-doc-submit>
                    <?= icon('upload', 'w-4 h-4 shrink-0') ?>
                    <span>Upload</span>
                </button>
            </div>
            <p data-doc-error class="field-error hidden mt-2"></p>
        </form>
    </div>
    <?php endforeach; ?>
</div>
