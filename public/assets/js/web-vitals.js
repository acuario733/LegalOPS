'use strict';

/**
 * Web Vitals minimal reporter.
 * Measures LCP, CLS, FID/INP and sends to /api/vitals (fire-and-forget).
 */
(function () {
    if (typeof window === 'undefined') return;

    function send(metric) {
        const body = JSON.stringify({
            name:  metric.name,
            value: Math.round(metric.name === 'CLS' ? metric.value * 1000 : metric.value),
            id:    metric.id,
            page:  window.location.pathname,
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/vitals', new Blob([body], { type: 'application/json' }));
        }
    }

    // Register Service Worker con versioning dinámico.
    // SW_VERSION es inyectado por PHP (BuildHelper::buildHash()) en el layout.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swVersion = (window.SW_VERSION || 'dev');
            navigator.serviceWorker.register('/sw.js?v=' + swVersion, { scope: '/' })
                .then(registration => {
                    registration.addEventListener('updatefound', () => {
                        const newWorker = registration.installing;
                        if (!newWorker) return;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                // Nueva versión disponible — notificar al sistema
                                document.dispatchEvent(new CustomEvent('sw:update-available', {
                                    detail: { registration },
                                }));
                            }
                        });
                    });
                })
                .catch(() => {/* silencioso en producción */});
        });
    }

    // ── LCP ────────────────────────────────────────────────────
    if (typeof PerformanceObserver !== 'undefined') {
        try {
            new PerformanceObserver(list => {
                const entries = list.getEntries();
                const last = entries[entries.length - 1];
                send({ name: 'LCP', value: last.startTime, id: 'lcp' });
            }).observe({ type: 'largest-contentful-paint', buffered: true });
        } catch (_) {/* unsupported */}

        // ── CLS ──────────────────────────────────────────────────
        try {
            let clsValue = 0;
            new PerformanceObserver(list => {
                list.getEntries().forEach(e => {
                    if (!e.hadRecentInput) clsValue += e.value;
                });
                send({ name: 'CLS', value: clsValue, id: 'cls' });
            }).observe({ type: 'layout-shift', buffered: true });
        } catch (_) {/* unsupported */}

        // ── FID / INP ────────────────────────────────────────────
        try {
            new PerformanceObserver(list => {
                list.getEntries().forEach(e => {
                    send({ name: 'FID', value: e.processingStart - e.startTime, id: 'fid' });
                });
            }).observe({ type: 'first-input', buffered: true });
        } catch (_) {/* unsupported */}
    }
})();
