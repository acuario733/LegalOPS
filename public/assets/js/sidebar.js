'use strict';

/**
 * sidebar.js — Única fuente de verdad para sidebar desktop + mobile.
 * Reemplaza sidebar.js + mobile-sidebar.js (eliminados del layout).
 *
 * Desktop: body.sidebar-collapsed controla ancho vía CSS.
 * Mobile: aside.sidebar-mobile-open + overlay.
 * Estado desktop persiste en localStorage.
 */
(function () {
    const STORAGE_KEY   = 'legalops-sidebar-collapsed';
    const BREAKPOINT    = 992;

    const sidebar      = document.getElementById('appSidebar');
    const collapseBtn  = document.getElementById('sidebarCollapseBtn');
    const collapseIcon = document.getElementById('sidebarCollapseIcon');
    const mobileBtn    = document.getElementById('mobileSidebarBtn');

    if (!sidebar) return;

    // ── Overlay (creado una sola vez, compartido) ──────────────────────────
    let overlay = null;

    function getOverlay() {
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);
            overlay.addEventListener('click', closeMobile);
        }
        return overlay;
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    function isMobile() { return window.innerWidth < BREAKPOINT; }

    function setCollapseIcon(collapsed) {
        if (!collapseIcon) return;
        collapseIcon.className = collapsed
            ? 'bi bi-chevron-double-right'
            : 'bi bi-chevron-double-left';
    }

    function setMobileBtnState(isOpen) {
        if (!mobileBtn) return;
        mobileBtn.setAttribute('aria-expanded', String(isOpen));
        mobileBtn.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
    }

    // ── Desktop: colapsar / expandir ───────────────────────────────────────
    function collapseDesktop() {
        document.body.classList.add('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, '1');
        setCollapseIcon(true);
    }

    function expandDesktop() {
        document.body.classList.remove('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, '0');
        setCollapseIcon(false);
    }

    function toggleDesktop() {
        document.body.classList.contains('sidebar-collapsed')
            ? expandDesktop()
            : collapseDesktop();
    }

    // ── Mobile: drawer ─────────────────────────────────────────────────────
    function openMobile() {
        sidebar.classList.add('sidebar-mobile-open');
        document.body.style.overflow = 'hidden';
        getOverlay().classList.add('show');
        setMobileBtnState(true);
    }

    function closeMobile() {
        sidebar.classList.remove('sidebar-mobile-open');
        document.body.style.overflow = '';
        if (overlay) overlay.classList.remove('show');
        setMobileBtnState(false);
    }

    function toggleMobile() {
        sidebar.classList.contains('sidebar-mobile-open')
            ? closeMobile()
            : openMobile();
    }

    // ── Restaurar estado al cargar ─────────────────────────────────────────
    function restoreState() {
        if (isMobile()) return;
        if (localStorage.getItem(STORAGE_KEY) === '1') {
            document.body.classList.add('sidebar-collapsed');
            setCollapseIcon(true);
        } else {
            setCollapseIcon(false);
        }
    }

    // ── Bindings ────────────────────────────────────────────────────────────
    if (collapseBtn) {
        collapseBtn.addEventListener('click', () => {
            isMobile() ? closeMobile() : toggleDesktop();
        });
    }

    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            isMobile() ? toggleMobile() : toggleDesktop();
        });
    }

    // Cerrar sidebar al navegar en mobile
    sidebar.querySelectorAll('.nav-link[href]').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) setTimeout(closeMobile, 150);
        });
    });

    // Cerrar drawer al pasar a desktop
    window.addEventListener('resize', () => {
        if (!isMobile()) closeMobile();
    });

    // ── Init ──────────────────────────────────────────────────────────────
    restoreState();
    setMobileBtnState(false);

    // ── API pública ───────────────────────────────────────────────────────
    window.LegalSidebar = {
        collapse:   collapseDesktop,
        expand:     expandDesktop,
        toggle:     toggleDesktop,
        openMobile: openMobile,
        closeMobile:closeMobile,
        close:      closeMobile,         // alias compat
    };
}());
