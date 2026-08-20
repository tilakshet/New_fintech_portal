<?php
/**
 * Renders a <dialog>-based modal skeleton. Opening/closing, focus-trap,
 * Escape handling and focus-return are handled generically by
 * assets/js/app.js for any element with [data-modal-trigger] / <dialog>.
 *
 * $id: unique dialog id, referenced by data-modal-trigger="$id"
 * $title: accessible dialog name
 * $bodyHtml: pre-rendered inner HTML for the modal body (already escaped by caller)
 * $primaryLabel/$primaryAttrs: primary action button
 * $danger: use the danger button style for the primary action
 */
function render_modal(string $id, string $title, string $bodyHtml, string $primaryLabel, string $primaryAttrs = '', bool $danger = false): void
{
    $primaryClass = $danger ? 'btn-danger' : 'btn-primary';
    ?>
    <dialog id="<?= e($id) ?>" class="rounded-md p-0 backdrop:bg-black/40 w-full max-w-md" aria-labelledby="<?= e($id) ?>-title">
        <form method="dialog" class="flex flex-col">
            <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-border">
                <h2 id="<?= e($id) ?>-title" class="text-3xl font-semibold text-text-primary"><?= e($title) ?></h2>
                <button type="button" class="btn-icon" data-modal-close aria-label="Close dialog"><?= icon('close', 'w-5 h-5') ?></button>
            </div>
            <div class="px-6 py-5 text-md text-text-secondary">
                <?= $bodyHtml ?>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-border bg-surface-muted rounded-b-md">
                <button type="button" class="btn-secondary" data-modal-close>Cancel</button>
                <button type="button" class="<?= $primaryClass ?>" <?= $primaryAttrs ?>><?= e($primaryLabel) ?></button>
            </div>
        </form>
    </dialog>
    <?php
}
