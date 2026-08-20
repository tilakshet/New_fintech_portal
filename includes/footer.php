    </main>

    <footer class="px-4 py-5 sm:px-6 lg:px-8 text-sm text-text-secondary border-t border-border">
        <div class="max-w-[1440px] mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>© <?= date('Y') ?> Verapay. All rights reserved.</span>
            <span class="flex items-center gap-4">
                <a href="/support" class="hover:text-text-primary hover:underline">Contact support</a>
                <a href="/settings" class="hover:text-text-primary hover:underline">Account settings</a>
            </span>
        </div>
    </footer>
</div>

<div id="toast-region" class="fixed bottom-4 right-4 z-50 flex flex-col gap-3 w-full max-w-sm" role="region" aria-label="Notifications" aria-live="polite"></div>

<script src="/assets/js/app.js" defer></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $src): ?>
<script src="<?= e($src) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
