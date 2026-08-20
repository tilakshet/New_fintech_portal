(function () {
    'use strict';
    window.Verapay = window.Verapay || {};

    /**
     * Single-series daily bar chart (e.g. deposit amount or withdrawal
     * amount per day). points: [{ label, value }]
     *
     * Renders into containerEl a wrapper holding the SVG plus a floating
     * tooltip. Days with a value of 0 get a short muted "no activity"
     * stub instead of a near-invisible 1px sliver — with sparse demo
     * data (a week where only one day has any transactions) a plain
     * proportional bar chart reads as a flat line, which is the actual
     * bug this replaces, not just a style pass.
     */
    function renderTrendChart(containerEl, points, color, unitLabel, titleId) {
        if (!containerEl || !points?.length) return;

        const width = 500;
        const height = 230;
        const padding = { top: 28, right: 12, bottom: 30, left: 12 };
        const chartW = width - padding.left - padding.right;
        const chartH = height - padding.top - padding.bottom;
        const maxVal = Math.max(1, ...points.map((p) => p.value));
        const groupW = chartW / points.length;
        const barW = Math.min(34, groupW * 0.46);
        const EMPTY_STUB_H = 6;

        let gridlines = '';
        [0, 0.25, 0.5, 0.75, 1].forEach((frac) => {
            const y = padding.top + chartH * frac;
            gridlines += `<line x1="${padding.left}" y1="${y.toFixed(1)}" x2="${width - padding.right}" y2="${y.toFixed(1)}" stroke="var(--color-border-default)" stroke-width="1" stroke-dasharray="${frac === 1 ? 'none' : '2 3'}"/>`;
        });

        let bars = '';
        let hitAreas = '';
        let labels = '';
        const meta = [];

        points.forEach((p, i) => {
            const cx = padding.left + groupW * i + groupW / 2;
            const hasValue = p.value > 0;
            const barH = hasValue ? (p.value / maxVal) * chartH : EMPTY_STUB_H;
            const barY = padding.top + chartH - barH;
            const barX = (cx - barW / 2).toFixed(1);

            bars += hasValue
                ? `<rect class="trend-bar" data-i="${i}" x="${barX}" y="${barY.toFixed(1)}" width="${barW}" height="${barH.toFixed(1)}" rx="4" fill="${color}">
                       <title>${p.label}: ${unitLabel(p.value)}</title>
                   </rect>`
                : `<rect class="trend-bar-empty" data-i="${i}" x="${barX}" y="${barY.toFixed(1)}" width="${barW}" height="${barH}" rx="3" fill="var(--color-border-strong)" opacity="0.5">
                       <title>${p.label}: no activity</title>
                   </rect>`;

            hitAreas += `<rect class="trend-hit" data-i="${i}" x="${(padding.left + groupW * i).toFixed(1)}" y="${padding.top}" width="${groupW.toFixed(1)}" height="${chartH}" fill="transparent"/>`;
            labels += `<text x="${cx.toFixed(1)}" y="${height - 10}" text-anchor="middle" font-size="11" fill="var(--color-text-secondary)">${p.label}</text>`;

            meta.push({
                xPct: (cx / width) * 100,
                topPct: (barY / height) * 100,
                label: p.label,
                text: hasValue ? unitLabel(p.value) : 'No activity',
            });
        });

        containerEl.innerHTML = `
            <div class="trend-chart">
                <svg viewBox="0 0 ${width} ${height}" class="w-full h-auto" role="img" aria-labelledby="${titleId}">
                    <title id="${titleId}">Daily volume, last ${points.length} days</title>
                    ${gridlines}
                    ${bars}
                    ${labels}
                    ${hitAreas}
                </svg>
                <div class="trend-tooltip" role="status" aria-hidden="true"></div>
            </div>
            <p class="sr-only">${points.map((p) => `${p.label}: ${p.value > 0 ? unitLabel(p.value) : 'no activity'}.`).join(' ')}</p>`;

        const wrapper = containerEl.querySelector('.trend-chart');
        const tooltip = containerEl.querySelector('.trend-tooltip');
        const barEls = containerEl.querySelectorAll('.trend-bar, .trend-bar-empty');

        function showTooltip(i) {
            const m = meta[i];
            if (!m) return;
            tooltip.innerHTML = `<span class="block text-xs font-semibold text-text-primary">${m.label}</span><span class="block text-sm font-semibold ${m.text === 'No activity' ? 'text-text-secondary' : 'text-text-primary'}">${m.text}</span>`;
            tooltip.style.left = m.xPct + '%';
            tooltip.style.top = Math.max(m.topPct, 4) + '%';
            tooltip.classList.add('is-visible');
            barEls.forEach((el) => el.classList.toggle('is-active', el.dataset.i === String(i)));
        }
        function hideTooltip() {
            tooltip.classList.remove('is-visible');
            barEls.forEach((el) => el.classList.remove('is-active'));
        }

        containerEl.querySelectorAll('.trend-hit').forEach((hit) => {
            const i = hit.dataset.i;
            hit.addEventListener('mouseenter', () => showTooltip(i));
            hit.addEventListener('mouseleave', hideTooltip);
            hit.addEventListener('touchstart', () => showTooltip(i), { passive: true });
        });
        wrapper.addEventListener('mouseleave', hideTooltip);
    }

    window.Verapay.renderTrendChart = renderTrendChart;
})();
