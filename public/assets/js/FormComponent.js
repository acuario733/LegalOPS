'use strict';

/**
 * FormComponent — formularios AJAX con validación on-blur.
 *
 * HTML mínimo:
 *   <form data-ajax-form action="/ruta" method="post">
 *     <input id="email" name="email" data-field-validator="required|email">
 *     <small id="email-error"></small>
 *     <button data-submit-btn>Enviar <span data-spinner hidden></span></button>
 *   </form>
 */
class FormComponent extends Component {
    constructor(selectorOrElement) {
        super(selectorOrElement);
        this._submitting = false;
    }

    mount() {
        if (!super.mount()) return false;
        this._setupValidation();
        this._setupSubmit();
        return true;
    }

    // ── Validación blur ──────────────────────────────────────────────────────

    _setupValidation() {
        this.on(this.el, 'blur', e => {
            const field = e.target.closest('[data-field-validator]');
            if (field) this.validateField(field);
        }, true);

        this.on(this.el, 'input', e => {
            const field = e.target.closest('[data-field-validator].is-invalid');
            if (field) this.validateField(field);
        });
    }

    validateField(field) {
        const rules   = field.dataset.fieldValidator.split('|');
        const errorEl = document.getElementById(`${field.id}-error`);

        if (!errorEl) return;

        errorEl.hidden = true;
        errorEl.textContent = '';
        field.classList.remove('is-invalid');

        const v = window.LegalOPS?.validators ?? {};
        const messages = window.LegalOPS?.validationMessages ?? {};

        for (const rule of rules) {
            const [name, param] = rule.split(':');
            if (!v[name]) continue;
            if (!v[name](field.value, param)) {
                const label = field.name.replace(/_/g, ' ');
                errorEl.textContent = messages[name]?.(param ?? label) ?? 'Campo inválido';
                errorEl.hidden = false;
                field.classList.add('is-invalid');
                break;
            }
        }
    }

    clearErrors() {
        this.selectAll('[data-field-validator]').forEach(f => {
            f.classList.remove('is-invalid');
            const err = document.getElementById(`${f.id}-error`);
            if (err) err.hidden = true;
        });
    }

    displayValidationErrors(errors) {
        Object.entries(errors).forEach(([name, msgs]) => {
            const input   = this.el.querySelector(`[name="${name}"]`);
            const errorEl = input ? document.getElementById(`${input.id}-error`) : null;
            if (input)   input.classList.add('is-invalid');
            if (errorEl) {
                errorEl.textContent = Array.isArray(msgs) ? msgs[0] : (msgs[0] ?? msgs);
                errorEl.hidden = false;
            }
        });
    }

    // ── Submit AJAX ──────────────────────────────────────────────────────────

    _setupSubmit() {
        this.on(this.el, 'submit', async e => {
            e.preventDefault();
            if (this._submitting) return;
            await this._handleSubmit();
        });
    }

    async _handleSubmit() {
        this._submitting = true;
        const btn     = this.select('[data-submit-btn]');
        const spinner = btn?.querySelector('[data-spinner]');

        if (btn)     btn.disabled    = true;
        if (spinner) spinner.hidden  = false;

        try {
            const body = {};
            new FormData(this.el).forEach((v, k) => { body[k] = v; });

            const payload = await window.LegalOPS.request(this.el.action, {
                method:  this.el.method.toUpperCase() || 'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(body),
            });

            this.emit('showNotification', { message: payload.message || 'Operación exitosa', type: 'success' });
            this.el.reset();
            this.clearErrors();
            if (payload.redirect) setTimeout(() => { window.location.href = payload.redirect; }, 1500);

        } catch (err) {
            if (err.payload?.validation) this.displayValidationErrors(err.payload.validation);
            this.emit('showNotification', { message: err.message || 'Error en el servidor', type: 'error' });
        } finally {
            this._submitting = false;
            if (btn)     btn.disabled   = false;
            if (spinner) spinner.hidden = true;
        }
    }

    getValues() {
        return Object.fromEntries(new FormData(this.el));
    }

    setValues(data) {
        Object.entries(data).forEach(([k, v]) => {
            const f = this.el.querySelector(`[name="${k}"]`);
            if (f) f.value = v;
        });
    }
}

if (typeof module !== 'undefined') module.exports = { FormComponent };
