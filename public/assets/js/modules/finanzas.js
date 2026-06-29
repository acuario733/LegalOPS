(() => {
    'use strict';

    const palette = {
        pendiente: 'text-bg-warning',
        parcial: 'text-bg-info',
        pagado: 'text-bg-success',
        registrado: 'text-bg-success',
        anulado: 'text-bg-secondary',
        cancelado: 'text-bg-secondary',
    };

    document.querySelectorAll('[data-finance-state]').forEach((badge) => {
        const state = badge.textContent.trim();
        badge.className = `badge ${palette[state] || 'text-bg-secondary'}`;
    });

    document.querySelectorAll('[data-balance-value]').forEach((element) => {
        const value = Number(element.textContent.replace(/,/g, ''));
        element.classList.add(value > 0 ? 'text-danger' : value < 0 ? 'text-success' : 'text-secondary');
    });

    const ensureOption = (select, value, label, notify = false) => {
        if (!select || value === undefined || value === null || value === '') return;
        let option = Array.from(select.options).find((item) => item.value === String(value));
        if (!option) {
            option = document.createElement('option');
            option.value = String(value);
            option.textContent = label || String(value);
            select.append(option);
        }
        option.selected = true;
        select.disabled = false;
        if (notify) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    document.querySelectorAll('[data-payment-form]').forEach((form) => {
        const honorario = form.querySelector('[data-payment-honorario]');
        const client = form.querySelector('[data-payment-client]');
        const caseSelect = form.querySelector('[data-payment-case]');
        const amount = form.querySelector('[data-payment-amount]');
        const currency = form.querySelector('[data-payment-currency]');
        honorario?.addEventListener('change', () => {
            const selected = honorario.selectedOptions[0];
            if (!selected || honorario.value === '') {
                if (amount) amount.removeAttribute('max');
                return;
            }
            ensureOption(client, selected.dataset.clienteId, selected.dataset.cliente);
            if (selected.dataset.casoId) {
                ensureOption(caseSelect, selected.dataset.casoId, selected.dataset.caso);
            } else if (caseSelect) {
                caseSelect.value = '';
            }
            if (currency && selected.dataset.moneda) {
                currency.value = selected.dataset.moneda;
            }
            if (amount && selected.dataset.saldo) {
                amount.max = selected.dataset.saldo;
            }
        });
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-payment-reveal]');
        if (!button) return;
        button.disabled = true;
        try {
            const payload = await window.LegalOPS.request(button.dataset.paymentReveal, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            });
            const target = document.querySelector(`[data-payment-reference="${button.dataset.target}"]`);
            if (target) target.textContent = payload.data.valor || '';
        } catch (error) {
            const container = document.querySelector('[data-feedback]');
            if (container) {
                container.className = 'alert alert-danger';
                container.textContent = error.message;
                container.hidden = false;
            }
        } finally {
            button.disabled = false;
        }
    });
})();
