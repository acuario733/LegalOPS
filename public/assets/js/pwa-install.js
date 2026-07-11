'use strict';

/**
 * pwa-install.js — Gestión del banner de instalación PWA.
 *
 * Usa window.__pwaPrompt capturado en el <head> de app.php para evitar
 * perder el evento beforeinstallprompt en conexiones lentas.
 */
(function () {
    const banner     = document.getElementById('pwa-install-banner');
    const installBtn = document.getElementById('pwa-install-btn');
    const dismissBtn = document.getElementById('pwa-dismiss-btn');

    // ── No mostrar si ya está instalada ───────────────────────────────────
    if (window.matchMedia('(display-mode: standalone)').matches) {
        if (banner) banner.style.display = 'none';
        return;
    }

    function updateInstallBtnState() {
        if (!installBtn) return;
        const hasPrompt = Boolean(window.__pwaPrompt);
        installBtn.disabled      = !hasPrompt;
        installBtn.style.opacity = hasPrompt ? '1' : '0.5';
        installBtn.style.cursor  = hasPrompt ? 'pointer' : 'not-allowed';
        installBtn.title         = hasPrompt ? '' : 'La instalación no está disponible en este momento.';
    }

    function showBanner() {
        if (!banner) return;
        if (localStorage.getItem('pwa-dismissed')) return;
        updateInstallBtnState();
        banner.style.display = 'flex';
    }

    // ── Mostrar si el prompt ya fue capturado antes de este script ─────────
    if (window.__pwaPrompt) {
        showBanner();
    }

    // ── Escuchar el evento custom que dispara el inline script del <head> ──
    document.addEventListener('pwa-prompt-ready', () => {
        showBanner();
    });

    // ── Botón Instalar ─────────────────────────────────────────────────────
    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (!window.__pwaPrompt) {
                if (window.LegalUI) {
                    window.LegalUI.toast('La instalación no está disponible en este momento.', 'error');
                }
                return;
            }

            try {
                window.__pwaPrompt.prompt();
                const { outcome } = await window.__pwaPrompt.userChoice;
                window.__pwaPrompt = null;
                if (banner) banner.style.display = 'none';

                if (typeof gtag === 'function') {
                    gtag('event', 'pwa_install', { event_category: 'PWA', event_label: outcome });
                }
            } catch {
                if (banner) banner.style.display = 'none';
            }
        });
    }

    // ── Botón Ahora no — funciona siempre, sin depender del prompt ─────────
    if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
            localStorage.setItem('pwa-dismissed', '1');
            if (banner) banner.style.display = 'none';
        });
    }

    // ── Instalación completada ──────────────────────────────────────────────
    window.addEventListener('appinstalled', () => {
        if (banner) banner.style.display = 'none';
        window.__pwaPrompt = null;
    });

    // ── Registrar Service Worker ────────────────────────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            const swVersion = window.SW_VERSION || 'dev';
            navigator.serviceWorker
                .register('/sw.js?v=' + swVersion)
                .then(reg => {
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
