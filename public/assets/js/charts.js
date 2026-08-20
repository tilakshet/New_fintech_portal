(function () {
    'use strict';
    window.Verapay = window.Verapay || {};

    /**
     * Renders a simple grouped bar chart (success vs failed volume per day)
     * as inline SVG into the given container. Each point also gets a
     * <title> tooltip and the container gets a visually-hidden text
     * summary so the trend is available to screen readers, not just color.
     *
     * points: [{ label, success, failed }]
     */
    function renderVolumeChart(containerEl, points) {
        if (!containerEl || !points?.length) return;

        const width = 560;
        const height = 200;
        const padding = { top: 10, right: 10, bottom: 28, left: 10 };
        const chartW = width - padding.left - padding.right;
        const chartH = height - padding.top - padding.bottom;
        const maxVal = Math.max(1, ...points.map((p) => p.success + p.failed));
        const groupW = chartW / points.length;
        const barW = Math.min(22, groupW * 0.32);

        let bars = '';
        let labels = '';
        points.forEach((p, i) => {
            const cx = padding.left + groupW * i + groupW / 2;
            const successH = (p.success / maxVal) * chartH;
            const failedH = (p.failed / maxVal) * chartH;
            const successX = cx - barW - 3;
            const failedX = cx + 3;

            bars += `
                <rect x="${successX}" y="${padding.top + chartH - successH}" width="${barW}" height="${Math.max(successH, 1)}" rx="3" fill="var(--color-success)" opacity="0.85">
                    <title>${p.label}: ${p.success} successful</title>
                </rect>
                <rect x="${failedX}" y="${padding.top + chartH - failedH}" width="${barW}" height="${Math.max(failedH, 1)}" rx="3" fill="var(--color-danger)" opacity="0.85">
                    <title>${p.label}: ${p.failed} failed</title>
                </rect>`;
            labels += `<text x="${cx}" y="${height - 8}" text-anchor="middle" font-size="10" fill="var(--color-text-secondary)">${p.label}</text>`;
        });

        containerEl.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" class="w-full h-auto" role="img" aria-labelledby="volume-chart-title">
                <title id="volume-chart-title">Successful vs failed payment volume by day</title>
                <line x1="${padding.left}" y1="${padding.top + chartH}" x2="${width - padding.right}" y2="${padding.top + chartH}" stroke="var(--color-border-default)" stroke-width="1"/>
                ${bars}
                ${labels}
            </svg>
            <p class="sr-only">
                ${points.map((p) => `${p.label}: ${p.success} successful, ${p.failed} failed.`).join(' ')}
            </p>`;
    }

    /**
     * Single-series daily bar chart (e.g. deposit amount or withdrawal
     * amount per day). points: [{ label, value }]
     */
    function renderTrendChart(containerEl, points, color, unitLabel, titleId) {
        if (!containerEl || !points?.length) return;

        const width = 400;
        const height = 180;
        const padding = { top: 10, right: 8, bottom: 26, left: 8 };
        const chartW = width - padding.left - padding.right;
        const chartH = height - padding.top - padding.bottom;
        const maxVal = Math.max(1, ...points.map((p) => p.value));
        const groupW = chartW / points.length;
        const barW = Math.min(26, groupW * 0.5);

        let bars = '';
        let labels = '';
        points.forEach((p, i) => {
            const cx = padding.left + groupW * i + groupW / 2;
            const barH = (p.value / maxVal) * chartH;
            bars += `
                <rect x="${(cx - barW / 2).toFixed(1)}" y="${(padding.top + chartH - barH).toFixed(1)}" width="${barW}" height="${Math.max(barH, 1)}" rx="3" fill="${color}" opacity="0.9">
                    <title>${p.label}: ${unitLabel(p.value)}</title>
                </rect>`;
            labels += `<text x="${cx}" y="${height - 8}" text-anchor="middle" font-size="10" fill="var(--color-text-secondary)">${p.label}</text>`;
        });

        containerEl.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" class="w-full h-auto" role="img" aria-labelledby="${titleId}">
                <title id="${titleId}">Daily volume, last ${points.length} days</title>
                <line x1="${padding.left}" y1="${padding.top + chartH}" x2="${width - padding.right}" y2="${padding.top + chartH}" stroke="var(--color-border-default)" stroke-width="1"/>
                ${bars}
                ${labels}
            </svg>
            <p class="sr-only">${points.map((p) => `${p.label}: ${unitLabel(p.value)}.`).join(' ')}</p>`;
    }

    /** Compact single-line trend sparkline. values: number[] */
    function renderSparkline(containerEl, values, color = 'var(--color-brand)') {
        if (!containerEl || !values?.length) return;
        const width = 160;
        const height = 40;
        const max = Math.max(...values);
        const min = Math.min(...values);
        const range = max - min || 1;
        const step = width / (values.length - 1 || 1);

        const points = values.map((v, i) => {
            const x = i * step;
            const y = height - ((v - min) / range) * (height - 4) - 2;
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');

        containerEl.innerHTML = `
            <svg viewBox="0 0 ${width} ${height}" class="w-full h-10" preserveAspectRatio="none" role="img" aria-label="Trend sparkline">
                <polyline points="${points}" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>`;
    }

    window.Verapay.renderVolumeChart = renderVolumeChart;
    window.Verapay.renderTrendChart = renderTrendChart;
    window.Verapay.renderSparkline = renderSparkline;
})();
