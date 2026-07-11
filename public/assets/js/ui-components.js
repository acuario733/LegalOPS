'use strict';

/**
 * ui-components.js — Componentes JS reutilizables para LegalOPS Cloud V2.
 * Expone el objeto global window.LegalUI.
 */
(function () {

    // ── A) TOAST NOTIFICATIONS ──────────────────────────────────────────────
    const TOAST_ICONS = {
        success: '<i class="bi bi-check-circle-fill"></i>',
        error:   '<i class="bi bi-x-circle-fill"></i>',
        warning: '<i class="bi bi-exclamation-triangle-fill"></i>',
        info:    '<i class="bi bi-info-circle-fill"></i>',
    };
    const TOAST_COLORS = {
        success: 'var(--color-green)',
        error:   'var(--color-danger)',
        warning: 'var(--color-warning)',
        info:    'var(--color-info)',
    };

    function getOrCreateToastContainer() {
        let container = document.querySelector('[data-notification-container]');
        if (!container) {
            container = document.createElement('div');
            container.setAttribute('data-notification-container', '');
            container.style.cssText =
                'position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;pointer-events:none;';
            document.body.appendChild(container);
        }
        return container;
    }

    function toast(message, type = 'success') {
        const container = getOrCreateToastContainer();
        const icon      = TOAST_ICONS[type] || TOAST_ICONS.info;
        const color     = TOAST_COLORS[type] || TOAST_COLORS.info;

        const el = document.createElement('div');
        el.style.cssText = [
            'background:var(--color-surface)',
            'border:1px solid var(--color-border)',
            'border-left:4px solid ' + color,
            'border-radius:var(--radius-md)',
            'box-shadow:var(--shadow-hover)',
            'padding:12px 16px',
            'margin-bottom:8px',
            'display:flex',
            'align-items:center',
            'gap:10px',
            'font-family:var(--font-base)',
            'font-size:var(--font-size-sm)',
            'color:var(--color-text-primary)',
            'pointer-events:all',
            'transform:translateX(120%)',
            'transition:transform 0.3s ease, opacity 0.3s ease',
            'opacity:0',
        ].join(';');

        el.innerHTML = `<span style="color:${color};font-size:18px;flex-shrink:0">${icon}</span>`
            + `<span style="flex:1">${message}</span>`
            + `<button style="background:none;border:none;cursor:pointer;color:var(--color-text-muted);font-size:16px;padding:0;line-height:1" aria-label="Cerrar">&times;</button>`;

        el.querySelector('button').addEventListener('click', () => dismiss(el));
        container.appendChild(el);

        // Slide in
        requestAnimationFrame(() => {
            el.style.transform = 'translateX(0)';
            el.style.opacity   = '1';
        });

        const timer = setTimeout(() => dismiss(el), 4000);

        function dismiss(node) {
            clearTimeout(timer);
            node.style.transform = 'translateX(120%)';
            node.style.opacity   = '0';
            setTimeout(() => node.remove(), 300);
        }

        return el;
    }

    // ── B) CONFIRM DIALOG ───────────────────────────────────────────────────
    function confirm(message, onConfirm, options = {}) {
        const title       = options.title       || 'Confirmar acción';
        const confirmText = options.confirmText || 'Confirmar';
        const type        = options.type        || 'primary';

        // Reutilizar modal existente o crear uno
        let modal = document.getElementById('legalui-confirm-modal');
        if (modal) modal.remove();

        const btnClass = type === 'danger'
            ? 'style="background:var(--color-danger);border-color:var(--color-danger);color:#fff"'
            : '';

        modal = document.createElement('div');
        modal.id        = 'legalui-confirm-modal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('aria-modal', 'true');
        modal.innerHTML = `
<div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title">${title}</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
    </div>
    <div class="modal-body" style="font-size:var(--font-size-sm);color:var(--color-text-secondary)">
      ${message}
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
      <button type="button" class="btn btn-primary" id="legalui-confirm-ok" ${btnClass}>${confirmText}</button>
    </div>
  </div>
</div>`;

        document.body.appendChild(modal);

        const bsModal = new bootstrap.Modal(modal);
        modal.querySelector('#legalui-confirm-ok').addEventListener('click', () => {
            bsModal.hide();
            if (typeof onConfirm === 'function') onConfirm();
        });
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
        bsModal.show();
    }

    // ── C) COPY TO CLIPBOARD ────────────────────────────────────────────────
    function copyText(text, buttonEl) {
        navigator.clipboard.writeText(text).then(() => {
            if (!buttonEl) return;
            const original = buttonEl.innerHTML;
            buttonEl.innerHTML = '<i class="bi bi-check2 me-1"></i>¡Copiado!';
            buttonEl.disabled  = true;
            setTimeout(() => {
                buttonEl.innerHTML = original;
                buttonEl.disabled  = false;
            }, 2000);
        }).catch(() => {
            toast('No se pudo copiar al portapapeles', 'error');
        });
    }

    // ── D) TOGGLE SWITCHES ──────────────────────────────────────────────────
    function initToggles() {
        document.querySelectorAll('input[type="checkbox"].toggle-switch:not([data-toggle-init])').forEach(input => {
            input.setAttribute('data-toggle-init', '1');

            const wrapper = document.createElement('label');
            wrapper.style.cssText =
                'display:inline-flex;align-items:center;gap:8px;cursor:pointer;user-select:none;';

            const track = document.createElement('span');
            track.style.cssText = [
                'display:inline-block',
                'width:40px',
                'height:22px',
                'border-radius:11px',
                'background:' + (input.checked ? 'var(--color-green)' : 'var(--color-border-dark)'),
                'position:relative',
                'transition:background 0.2s',
                'flex-shrink:0',
            ].join(';');

            const thumb = document.createElement('span');
            thumb.style.cssText = [
                'display:block',
                'width:16px',
                'height:16px',
                'border-radius:50%',
                'background:#fff',
                'position:absolute',
                'top:3px',
                'left:' + (input.checked ? '21px' : '3px'),
                'transition:left 0.2s',
                'box-shadow:0 1px 3px rgba(0,0,0,0.2)',
            ].join(';');

            track.appendChild(thumb);
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            wrapper.appendChild(track);

            input.style.cssText = 'position:absolute;opacity:0;width:0;height:0;';

            input.addEventListener('change', () => {
                track.style.background = input.checked
                    ? 'var(--color-green)' : 'var(--color-border-dark)';
                thumb.style.left = input.checked ? '21px' : '3px';
            });
        });
    }

    // ── E) DROPDOWN MENUS ───────────────────────────────────────────────────
    function initDropdowns() {
        document.addEventListener('click', (e) => {
            // Cerrar todos los dropdowns custom al hacer clic fuera
            document.querySelectorAll('.legalui-dropdown.open').forEach(dd => {
                if (!dd.contains(e.target)) dd.classList.remove('open');
            });

            // Abrir/cerrar el dropdown clickeado
            const trigger = e.target.closest('[data-legalui-dropdown]');
            if (trigger) {
                e.preventDefault();
                const dd = trigger.closest('.legalui-dropdown');
                if (dd) {
                    const wasOpen = dd.classList.contains('open');
                    document.querySelectorAll('.legalui-dropdown.open')
                        .forEach(d => d.classList.remove('open'));
                    if (!wasOpen) dd.classList.add('open');
                }
            }
        });
    }

    // ── F) STAT COUNTER ANIMATION ───────────────────────────────────────────
    function animateCounters() {
        const els = document.querySelectorAll('.stat-value[data-count]');
        if (!els.length) return;

        const easeOut = (t) => 1 - Math.pow(1 - t, 3);

        els.forEach(el => {
            const target   = parseFloat(el.getAttribute('data-count')) || 0;
            const prefix   = el.getAttribute('data-prefix') || '';
            const suffix   = el.getAttribute('data-suffix') || '';
            const decimals = el.getAttribute('data-decimals') ? parseInt(el.getAttribute('data-decimals')) : 0;
            const duration = 800;
            const start    = performance.now();

            function step(now) {
                const elapsed  = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const value    = target * easeOut(progress);
                el.textContent = prefix + value.toFixed(decimals) + suffix;
                if (progress < 1) requestAnimationFrame(step);
            }

            requestAnimationFrame(step);
        });
    }

    // ── INIT AUTOMÁTICO ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        initToggles();
        initDropdowns();
        animateCounters();

        // Botones "copiar" con data-copy
        document.querySelectorAll('[data-copy]').forEach(btn => {
            btn.addEventListener('click', () => {
                copyText(btn.getAttribute('data-copy'), btn);
            });
        });

        // Botones de copia específicos de módulos anteriores
        document.querySelectorAll('[data-copy-booking]').forEach(btn => {
            btn.addEventListener('click', () => {
                copyText(btn.getAttribute('data-copy-booking'), btn);
            });
        });
    });

    // ── EXPORTS ──────────────────────────────────────────────────────────────
    window.LegalUI = {
        toast,
        confirm,
        copyText,
        initToggles,
        initDropdowns,
        animateCounters,
    };

}());
