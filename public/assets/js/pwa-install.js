'use strict';

(function () {
    let deferredPrompt = null;

    const banner      = document.getElementById('pwa-install-banner');
    const installBtn  = document.getElementById('pwa-install-btn');
    const dismissBtn  = document.getElementById('pwa-dismiss-btn');

    // ── Ocultar si ya está instalada (modo standalone) ───────────────────────
    if (window.matchMedia('(display-mode: standalone)').matches) {
        if (banner) banner.style.display = 'none';
    }

    // ── Captura el evento beforeinstallprompt ────────────────────────────────
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;

        if (banner && !localStorage.getItem('pwa-dismissed')) {
            banner.style.display = 'flex';
        }
    });

    // ── Botón Instalar ───────────────────────────────────────────────────────
    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) return;

            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;

            if (banner) banner.style.display = 'none';

            // Registrar resultado en analytics si está disponible (opcional)
            if (typeof gtag === 'function') {
                gtag('event', 'pwa_install', { event_category: 'PWA', event_label: outcome });
            }
        });
    }

    // ── Botón Ahora no ───────────────────────────────────────────────────────
    if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
            localStorage.setItem('pwa-dismissed', '1');
            if (banner) banner.style.display = 'none';
        });
    }

    // ── Detectar instalación completada ─────────────────────────────────────
    window.addEventListener('appinstalled', () => {
        if (banner) banner.style.display = 'none';
        deferredPrompt = null;
    });

    // ── Registrar Service Worker ─────────────────────────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swVersion = window.SW_VERSION || 'dev';
            navigator.serviceWorker
                .register('/sw.js?v=' + swVersion)
                .then(reg => {
                    console.log('[PWA] SW registrado:', reg.scope);

                    // Notificar al SW activo cuando hay una nueva versión disponible
                    reg.addEventListener('updatefound', () => {
                        const newSW = reg.installing;
                        if (!newSW) return;
                        newSW.addEventListener('statechange', () => {
                            if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
                                newSW.postMessage({ type: 'SKIP_WAITING' });
                            }
                        });
                    });
                })
                .catch(err => console.error('[PWA] Error SW:', err));
        });
    }
}());
