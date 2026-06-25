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
