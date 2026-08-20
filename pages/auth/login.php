<?php
/**
 * Standalone page (no sidebar/navbar chrome). $route === 'login', reached
 * only when unauthenticated (public/index.php redirects logged-in users away).
 */
$error = null;
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Sign in · Verapay</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.build.css">
</head>
<body class="bg-surface-strong min-h-screen flex items-center justify-center px-4 py-10 antialiased">
    <div class="w-full max-w-md">
        <div class="flex items-center justify-center gap-2.5 mb-8">
            <span class="flex items-center justify-center w-9 h-9 rounded-sm bg-brand text-white font-bold text-lg" aria-hidden="true">V</span>
            <span class="text-3xl font-bold text-text-inverse tracking-tight">Verapay</span>
        </div>

        <div class="card">
            <h1 class="text-3xl font-semibold text-text-primary mb-1.5">Sign in</h1>
            <p class="text-md text-text-secondary mb-6">Secure access to your Verapay account.</p>

            <?php if ($error): ?>
                <div class="mb-5 flex items-start gap-2.5 rounded-sm border border-danger/30 bg-danger-bg px-4 py-3 text-md text-danger" role="alert">
                    <?= icon('alert-circle', 'w-5 h-5 shrink-0 mt-0.5') ?>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form id="login-form" action="/api/auth/login.php" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="mb-5">
                    <label for="email" class="field-label">Email address</label>
                    <input type="email" id="email" name="email" class="field-input" autocomplete="username" required aria-describedby="email-error">
                    <p id="email-error" class="field-error hidden"></p>
                </div>

                <div class="mb-6">
                    <label for="password" class="field-label">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="field-input pr-11" autocomplete="current-password" required aria-describedby="password-error">
                        <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center px-3 text-text-secondary" aria-label="Show password" aria-pressed="false">
                            <?= icon('eye', 'w-5 h-5') ?>
                        </button>
                    </div>
                    <p id="password-error" class="field-error hidden"></p>
                </div>

                <button type="submit" id="login-submit" class="btn-primary w-full">Sign in</button>
            </form>
        </div>

        <p class="mt-6 text-center text-sm text-text-inverse/60">
            Demo accounts: admin@verapay.test · operator@verapay.test · priya@verapay.test
        </p>
    </div>

    <div id="toast-region" class="fixed bottom-4 right-4 z-50 flex flex-col gap-3 w-full max-w-sm" role="region" aria-label="Notifications" aria-live="polite"></div>

    <script>
        document.getElementById('toggle-password').addEventListener('click', function () {
            const input = document.getElementById('password');
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!showing));
            this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });

        document.getElementById('login-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = document.getElementById('login-submit');
            const emailError = document.getElementById('email-error');
            const passwordError = document.getElementById('password-error');
            emailError.classList.add('hidden');
            passwordError.classList.add('hidden');
            document.getElementById('email').setAttribute('aria-invalid', 'false');
            document.getElementById('password').setAttribute('aria-invalid', 'false');

            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');

            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                const body = await res.json();
                if (body.success) {
                    window.location.href = '/dashboard';
                    return;
                }
                passwordError.textContent = body.message || 'Unable to sign in.';
                passwordError.classList.remove('hidden');
                document.getElementById('password').setAttribute('aria-invalid', 'true');
            } catch (err) {
                passwordError.textContent = 'Network error. Please try again.';
                passwordError.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('btn-loading');
            }
        });
    </script>
</body>
</html>
