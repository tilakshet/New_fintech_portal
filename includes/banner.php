<?php
/**
 * Original decorative header banner for the dashboard/wallet/profile
 * pages — a gradient panel with an abstract "payment network" line
 * pattern (hand-built inline SVG, not a stock/competitor asset), whose
 * greeting and background both shift with the viewer's local time of
 * day. Server-rendered using server time as a same-paint fallback, then
 * corrected client-side to the viewer's actual local hour (the server
 * runs on UTC — see config.php — which is very often not the viewer's
 * timezone). Purely decorative: no data-bearing content lives only here.
 */
function render_hero_banner(array $user, string $eyebrow, string $blurb): void
{
    $hour = (int) date('G');

    // [greeting, gradient classes, accent icon] per time-of-day band.
    // Kept in sync with the identical TIME_BANDS array in the inline
    // script below, which re-evaluates this against the viewer's local time.
    $timeBands = [
        ['from' => 5, 'to' => 11, 'greeting' => 'Good morning', 'gradient' => ['from-amber-500', 'to-brand'], 'icon' => 'sun'],
        ['from' => 12, 'to' => 16, 'greeting' => 'Good afternoon', 'gradient' => ['from-brand', 'to-brand-emphasis'], 'icon' => 'sun'],
        ['from' => 17, 'to' => 20, 'greeting' => 'Good evening', 'gradient' => ['from-orange-600', 'to-brand-emphasis'], 'icon' => 'sunset'],
        ['from' => 21, 'to' => 24, 'greeting' => 'Good night', 'gradient' => ['from-slate-900', 'to-brand-emphasis'], 'icon' => 'moon'],
        ['from' => 0, 'to' => 4, 'greeting' => 'Good night', 'gradient' => ['from-slate-900', 'to-brand-emphasis'], 'icon' => 'moon'],
    ];
    $band = $timeBands[0];
    foreach ($timeBands as $candidate) {
        if ($hour >= $candidate['from'] && $hour <= $candidate['to']) {
            $band = $candidate;
            break;
        }
    }

    $firstName = trim(strtok($user['name'], ' '));
    $gradientClass = implode(' ', $band['gradient']);
    ?>
    <div id="hero-banner" class="relative overflow-hidden rounded-md mb-6 bg-gradient-to-br <?= e($gradientClass) ?> transition-colors duration-fast">
        <svg class="absolute inset-0 w-full h-full opacity-[0.14]" viewBox="0 0 800 220" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <g fill="none" stroke="#ffffff" stroke-width="1.4">
                <path d="M420 40 L560 90 L690 55"/>
                <path d="M560 90 L610 180"/>
                <path d="M420 40 L480 150"/>
                <path d="M480 150 L610 180 L730 140"/>
                <path d="M690 55 L730 140"/>
            </g>
            <g fill="#ffffff">
                <circle cx="420" cy="40" r="5"/>
                <circle cx="560" cy="90" r="6"/>
                <circle cx="690" cy="55" r="4"/>
                <circle cx="480" cy="150" r="5"/>
                <circle cx="610" cy="180" r="7"/>
                <circle cx="730" cy="140" r="4"/>
            </g>
        </svg>
        <div class="relative px-6 py-6 sm:px-8 sm:py-7 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex items-start gap-3">
                <span id="hero-icon" class="hidden sm:flex items-center justify-center w-11 h-11 rounded-md bg-white/15 text-white shrink-0 mt-0.5"><?= icon($band['icon'], 'w-6 h-6') ?></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold uppercase tracking-wide text-white/70"><?= e($eyebrow) ?></p>
                    <h2 class="text-4xl font-semibold text-white mt-1 truncate"><span id="hero-greeting"><?= e($band['greeting']) ?></span>, <?= e($firstName) ?></h2>
                    <p class="text-md text-white/80 mt-1.5 max-w-md"><?= e($blurb) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <span class="flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-sm font-medium text-white">
                    <span class="w-1.5 h-1.5 rounded-full bg-success" aria-hidden="true"></span>
                    Account secure
                </span>
                <span id="hero-clock" class="hidden sm:block text-sm text-white/70 font-mono" aria-live="off"></span>
            </div>
        </div>
    </div>
    <script>
        (function () {
            var banner = document.getElementById('hero-banner');
            var clockEl = document.getElementById('hero-clock');
            if (!banner) return;

            var TIME_BANDS = [
                { from: 5, to: 11, greeting: 'Good morning', gradient: ['from-amber-500', 'to-brand'],
                  icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.5M12 19v2.5M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2.5 12H5M19 12h2.5M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>' },
                { from: 12, to: 16, greeting: 'Good afternoon', gradient: ['from-brand', 'to-brand-emphasis'],
                  icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v2.5M12 19v2.5M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M2.5 12H5M19 12h2.5M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>' },
                { from: 17, to: 20, greeting: 'Good evening', gradient: ['from-orange-600', 'to-brand-emphasis'],
                  icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17.5h16"/><circle cx="12" cy="13" r="4"/><path d="M12 6.5V4M6.5 8.5 5 7M17.5 8.5 19 7"/><path d="M3 20.5h18"/></svg>' },
                { from: 21, to: 24, greeting: 'Good night', gradient: ['from-slate-900', 'to-brand-emphasis'],
                  icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.3A8 8 0 1 1 9.7 4.5a6.3 6.3 0 0 0 9.8 9.8Z"/></svg>' },
                { from: 0, to: 4, greeting: 'Good night', gradient: ['from-slate-900', 'to-brand-emphasis'],
                  icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.3A8 8 0 1 1 9.7 4.5a6.3 6.3 0 0 0 9.8 9.8Z"/></svg>' },
            ];
            var ALL_GRADIENT_CLASSES = TIME_BANDS.reduce(function (acc, b) { return acc.concat(b.gradient); }, []);

            function applyLocalBand() {
                var hour = new Date().getHours();
                var band = TIME_BANDS.find(function (b) { return hour >= b.from && hour <= b.to; }) || TIME_BANDS[0];

                banner.classList.remove.apply(banner.classList, ALL_GRADIENT_CLASSES);
                banner.classList.add.apply(banner.classList, band.gradient);

                var greetingEl = document.getElementById('hero-greeting');
                if (greetingEl) greetingEl.textContent = band.greeting;

                var iconEl = document.getElementById('hero-icon');
                if (iconEl) iconEl.innerHTML = band.icon;
            }

            applyLocalBand();

            if (clockEl) {
                var tick = function () {
                    clockEl.textContent = new Date().toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                };
                tick();
                setInterval(tick, 30000);
            }
        })();
    </script>
    <?php
}
