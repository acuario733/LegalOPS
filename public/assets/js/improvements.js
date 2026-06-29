/* global bootstrap */
'use strict';

// ─── EventBus ───────────────────────────────────────────────────────────────

class EventBus {
    constructor() {
        /** @type {Record<string, Function[]>} */
        this.events = {};
    }

    /** @returns {() => void} función para desuscribirse */
    on(event, callback) {
        if (!this.events[event]) this.events[event] = [];
        this.events[event].push(callback);
        return () => this.off(event, callback);
    }

    off(event, callback) {
        if (!this.events[event]) return;
        this.events[event] = this.events[event].filter(cb => cb !== callback);
    }

    emit(event, data) {
        if (!this.events[event]) return;
        this.events[event].forEach(cb => {
            try { cb(data); } catch (err) { console.error(`EventBus error en '${event}':`, err); }
        });
    }
}

window.LegalOPS = window.LegalOPS || {};
window.LegalOPS.eventBus = new EventBus();

// ─── Validadores (espejo de PHP) ─────────────────────────────────────────────

const validators = {
    required:  v => v != null && String(v).trim() !== '',
    email:     v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v)),
    minLength: (v, min) => !v || String(v).length >= parseInt(min, 10),
    maxLength: (v, max) => !v || String(v).length <= parseInt(max, 10),
    documento: v => /^[0-9]{6,}$/.test(String(v)),
    phone:     v => /^\+?[0-9\s\-()]{7,}$/.test(String(v)),
};

const validationMessages = {
    required:  field => `${field} es requerido`,
    email:     ()    => 'Email inválido',
    minLength: min   => `Mínimo ${min} caracteres`,
    maxLength: max   => `Máximo ${max} caracteres`,
    documento: ()    => 'Documento: mínimo 6 dígitos',
    phone:     ()    => 'Teléfono inválido (mínimo 7 caracteres)',
};

// ─── Validación on-blur ──────────────────────────────────────────────────────

document.addEventListener('blur', e => {
    if (!e.target.matches('[data-field-validator]')) return;

    const field   = e.target;
    const rules   = field.dataset.fieldValidator.split('|');
    const errorEl = document.getElementById(`${field.id}-error`);

    if (!errorEl) return;

    errorEl.textContent = '';
    errorEl.hidden = true;
    field.classList.remove('is-invalid');

    for (const rule of rules) {
        const [name, param] = rule.split(':');
        if (!validators[name]) continue;

        if (!validators[name](field.value, param)) {
            const label = field.name.replace(/_/g, ' ');
            errorEl.textContent = validationMessages[name]?.(param ?? label) ?? 'Campo inválido';
            errorEl.hidden = false;
            field.classList.add('is-invalid');
            break;
        }
    }
}, true);

// Limpiar error cuando campo vuelve a ser válido (input)
document.addEventListener('input', e => {
    if (!e.target.matches('[data-field-validator].is-invalid')) return;

    const field   = e.target;
    const rules   = field.dataset.fieldValidator.split('|');
    const errorEl = document.getElementById(`${field.id}-error`);

    const allValid = rules.every(rule => {
        const [name, param] = rule.split(':');
        return !validators[name] || validators[name](field.value, param);
    });

    if (allValid) {
        field.classList.remove('is-invalid');
        if (errorEl) errorEl.hidden = true;
    }
});

// ─── AJAX Form submit ────────────────────────────────────────────────────────

document.addEventListener('submit', async e => {
    if (!e.target.matches('[data-ajax-form]')) return;
    e.preventDefault();

    const form    = e.target;
    const btn     = form.querySelector('[data-submit-btn]');
    const spinner = btn?.querySelector('[data-spinner]');

    if (btn) btn.disabled = true;
    if (spinner) spinner.hidden = false;

    try {
        // Construir payload como JSON para enviar CSRF token correctamente
        const formData = new FormData(form);
        const body = {};
        formData.forEach((val, key) => { body[key] = val; });

        const payload = await window.LegalOPS.request(form.action, {
            method: form.method.toUpperCase() || 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        window.LegalOPS.eventBus.emit('showNotification', {
            message: payload.message || 'Operación exitosa',
            type: 'success',
        });

        form.reset();
        form.querySelectorAll('[data-field-validator]').forEach(f => {
            f.classList.remove('is-invalid');
            const err = document.getElementById(`${f.id}-error`);
            if (err) err.hidden = true;
        });

        if (payload.redirect) {
            setTimeout(() => { window.location.href = payload.redirect; }, 1500);
        }
    } catch (err) {
        if (err.payload?.validation) {
            Object.entries(err.payload.validation).forEach(([fieldName, msgs]) => {
                const input   = form.querySelector(`[name="${fieldName}"]`);
                const errorEl = document.getElementById(`${fieldName}-error`);
                if (input)   input.classList.add('is-invalid');
                if (errorEl) {
                    errorEl.textContent = Array.isArray(msgs) ? msgs[0] : msgs;
                    errorEl.hidden = false;
                }
            });
        }

        window.LegalOPS.eventBus.emit('showNotification', {
            message: err.message || 'Error en el servidor',
            type: 'error',
        });
    } finally {
        if (btn) btn.disabled = false;
        if (spinner) spinner.hidden = true;
    }
});

// ─── Toast notifications ─────────────────────────────────────────────────────

window.LegalOPS.eventBus.on('showNotification', ({ message, type = 'info', duration = 4000 }) => {
    const bgClass = type === 'error' ? 'bg-danger' : type === 'success' ? 'bg-success' : 'bg-info';

    const toastEl = Object.assign(document.createElement('div'), {
        className:   `toast align-items-center text-white ${bgClass} border-0 mb-2`,
        role:        'alert',
        ariaLive:    'assertive',
        ariaAtomic:  'true',
        innerHTML: `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto"
                        data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>`,
    });

    const container = document.querySelector('[data-notification-container]') || document.body;
    container.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl, { delay: duration });
    toast.show();

    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
});

console.log('✓ LegalOPS improvements.js cargado');

// Exportar para tests unitarios
if (typeof module !== 'undefined') module.exports = { EventBus, validators };
