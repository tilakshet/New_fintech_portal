<?php
/**
 * Original decorative header banner for the dashboard/wallet pages — a
 * brand-gradient panel with an abstract "payment network" line pattern
 * (hand-built inline SVG, not a stock/competitor asset). Purely
 * decorative: no data-bearing content lives only here.
 */
function render_hero_banner(array $user, string $eyebrow, string $blurb): void
{
    $hour = (int) date('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = trim(strtok($user['name'], ' '));
    ?>
    <div class="relative overflow-hidden rounded-md mb-6 bg-gradient-to-br from-brand to-brand-emphasis">
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
            <div class="min-w-0">
                <p class="text-sm font-semibold uppercase tracking-wide text-white/70"><?= e($eyebrow) ?></p>
                <h2 class="text-4xl font-semibold text-white mt-1 truncate"><?= e($greeting) ?>, <?= e($firstName) ?></h2>
                <p class="text-md text-white/80 mt-1.5 max-w-md"><?= e($blurb) ?></p>
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
            var el = document.getElementById('hero-clock');
            if (!el) return;
            function tick() {
                el.textContent = new Date().toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            }
            tick();
            setInterval(tick, 30000);
        })();
    </script>
    <?php
}
