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
<body class="min-h-screen antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-2">

        <!-- Brand panel — hidden on mobile, this is the "creative fintech" visual side -->
        <div class="hidden lg:flex relative overflow-hidden flex-col justify-between p-12 bg-gradient-to-br from-brand to-brand-emphasis">
            <svg class="absolute inset-0 w-full h-full opacity-[0.12]" viewBox="0 0 700 1000" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <g fill="none" stroke="#ffffff" stroke-width="1.4">
                    <path d="M60 120 L220 200 L180 340"/>
                    <path d="M220 200 L400 160 L520 260"/>
                    <path d="M180 340 L340 420 L300 560"/>
                    <path d="M520 260 L560 420 L420 500"/>
                    <path d="M300 560 L420 500 L480 660"/>
                    <path d="M480 660 L360 760 L420 880"/>
                    <path d="M60 120 L120 280"/>
                </g>
                <g fill="#ffffff">
                    <circle cx="60" cy="120" r="4.5"/>
                    <circle cx="220" cy="200" r="6"/>
                    <circle cx="400" cy="160" r="4"/>
                    <circle cx="520" cy="260" r="5.5"/>
                    <circle cx="180" cy="340" r="5"/>
                    <circle cx="340" cy="420" r="4"/>
                    <circle cx="560" cy="420" r="4.5"/>
                    <circle cx="300" cy="560" r="6"/>
                    <circle cx="420" cy="500" r="4"/>
                    <circle cx="480" cy="660" r="5"/>
                    <circle cx="360" cy="760" r="4.5"/>
                    <circle cx="420" cy="880" r="5"/>
                    <circle cx="120" cy="280" r="4"/>
                </g>
            </svg>

                        <div class="relative flex items-center gap-3">
                <span class="relative flex items-center justify-center w-11 h-11 shrink-0" aria-hidden="true">
                    <svg viewBox="0 0 40 40" class="w-11 h-11 drop-shadow-[0_4px_10px_rgba(0,0,0,0.25)]" fill="none">
                        <defs>
                            <linearGradient id="brand-logo-grad-top-a" x1="5" y1="4" x2="35" y2="20" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#ffffff"/>
                                <stop offset="1" stop-color="#e9d5ff"/>
                            </linearGradient>
                        </defs>
                        <polygon points="20,22 35,14.5 35,20.5 20,28 5,20.5 5,14.5" fill="#ffffff" opacity="0.35"/>
                        <polygon points="20,28 35,20.5 35,26.5 20,34 5,26.5 5,20.5" fill="#ffffff" opacity="0.2"/>
                        <polygon points="20,4 35,12 20,20 5,12" fill="url(#brand-logo-grad-top-a)"/>
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-2xl font-bold text-white tracking-tight">Verapay</span>
                    <span class="block text-[10px] font-semibold tracking-[0.18em] text-[#e9d5ff] uppercase">Fintech Portal</span>
                </span>
            </div>

            <div class="relative max-w-md">
                <h1 class="text-4xl font-semibold text-white leading-tight mb-4">Payments infrastructure built for scale.</h1>
                <p class="text-md text-white/75 mb-8">One dashboard for deposits, withdrawals, settlements, and merchant identity — built for teams that move money every day.</p>

                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-9 h-9 rounded-md bg-white/15 text-white shrink-0"><?= icon('shield', 'w-5 h-5') ?></span>
                        <span class="text-md text-white/90">Bank-grade encryption and audit trails</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-9 h-9 rounded-md bg-white/15 text-white shrink-0"><?= icon('wallet', 'w-5 h-5') ?></span>
                        <span class="text-md text-white/90">Real-time settlement and payout tracking</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="flex items-center justify-center w-9 h-9 rounded-md bg-white/15 text-white shrink-0"><?= icon('transactions', 'w-5 h-5') ?></span>
                        <span class="text-md text-white/90">Live transaction and KYC visibility</span>
                    </div>
                              </div>
            </div>

            <!-- Invisible spacer — preserves the same justify-between layout math as before (3 anchors: logo / headline / bottom), so removing the status card doesn't shift the headline's vertical position -->
            <div aria-hidden="true"></div>
        </div>

        <!-- Form panel -->
        <div class="flex items-center justify-center px-4 py-10 sm:py-16 bg-surface-strong lg:bg-surface-muted">
            <div class="w-full max-w-md">
                <div class="flex items-center justify-center gap-2.5 mb-8 lg:hidden">
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

                <p class="mt-6 text-center text-sm text-text-inverse/60 lg:text-text-secondary">
                    Demo accounts: admin@verapay.test · operator@verapay.test · priya@verapay.test
                </p>
            </div>
        </div>
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