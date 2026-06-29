'use strict';

/**
 * mobile-sidebar.js — Drawer lateral en mobile para LegalOPS Cloud.
 *
 * Trabaja junto al toggle nativo de AdminLTE (data-lte-toggle="sidebar").
 * Añade: overlay oscuro, bloqueo de scroll del body, y cierre al navegar.
 */
(function () {
    const BREAKPOINT = 992;

    function isMobile() {
        return window.innerWidth < BREAKPOINT;
    }

    function getSidebar() {
        return (
            document.querySelector('.app-sidebar') ||
            document.querySelector('#sidebar') ||
            document.querySelector('[data-role="sidebar"]')
        );
    }

    function getOrCreateOverlay() {
        let overlay = document.querySelector('.sidebar-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    function isSidebarOpen() {
        const sidebar = getSidebar();
        if (!sidebar) return false;
        return (
            sidebar.classList.contains('show') ||
            document.body.classList.contains('sidebar-open')
        );
    }

    function openSidebar() {
        const sidebar = getSidebar();
        if (!sidebar) return;

        sidebar.classList.add('show');
        document.body.classList.add('sidebar-open');
        document.body.style.overflow = 'hidden';

        const overlay = getOrCreateOverlay();
        overlay.classList.add('show');
        overlay.addEventListener('click', closeSidebar, { once: true });
    }

    function closeSidebar() {
        const sidebar = getSidebar();
        if (sidebar) {
            sidebar.classList.remove('show');
        }
        document.body.classList.remove('sidebar-open');
        document.body.style.overflow = '';

        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.classList.remove('show');
        }
    }

    function toggleSidebar() {
        isSidebarOpen() ? closeSidebar() : openSidebar();
    }

    document.addEventListener('DOMContentLoaded', () => {
        // ── Bind a todos los botones de toggle ──────────────────────────────
        const toggleBtns = document.querySelectorAll(
            '.sidebar-toggle-btn, [data-toggle="sidebar"], #sidebarToggle'
        );
        toggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Si AdminLTE ya manejó el evento, solo sincronizamos overlay/scroll
                // Si no, hacemos toggle manual
                if (isMobile()) {
                    e.preventDefault();
                    toggleSidebar();
                }
            });
        });

        // ── Cerrar sidebar en resize a desktop ──────────────────────────────
        window.addEventListener('resize', () => {
            if (!isMobile()) {
                closeSidebar();
            }
        });

        // ── Cerrar sidebar al navegar (links dentro del sidebar) ─────────────
        if (isMobile()) {
            const sidebar = getSidebar();
            if (sidebar) {
                sidebar.querySelectorAll('a[href]').forEach(link => {
                    link.addEventListener('click', () => {
                        setTimeout(closeSidebar, 150);
                    });
                });
            }
        }

        // ── Sincronizar overlay con estado AdminLTE ──────────────────────────
        // AdminLTE puede abrir el sidebar sin pasar por este script (teclas, etc.)
        const sidebar = getSidebar();
        if (sidebar) {
            const observer = new MutationObserver(() => {
                if (!isMobile()) return;
                const overlay = document.querySelector('.sidebar-overlay');
                if (!overlay) return;

                if (isSidebarOpen()) {
                    document.body.style.overflow = 'hidden';
                    overlay.classList.add('show');
                } else {
                    document.body.style.overflow = '';
                    overlay.classList.remove('show');
                }
            });

            observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
            observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        }
    });

    // ── API pública ──────────────────────────────────────────────────────────
    window.LegalSidebar = {
        open:   openSidebar,
        close:  closeSidebar,
        toggle: toggleSidebar,
    };
}());
