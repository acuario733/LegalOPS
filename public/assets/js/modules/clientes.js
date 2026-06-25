(() => {
    'use strict';

    const showMessage = (message, type = 'success') => {
        const container = document.querySelector('[data-feedback]');
        if (!container) return;
        container.className = `alert alert-${type}`;
        container.textContent = message;
        container.hidden = false;
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-client-reveal]');
        if (!button) return;
        button.disabled = true;
        try {
            const payload = await window.LegalOPS.request(button.dataset.clientReveal, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ campo: button.dataset.field }),
            });
            const target = document.querySelector(`[data-sensitive-target="${button.dataset.target}"]`);
            if (target) target.textContent = payload.data.valor || '';
            showMessage(payload.message);
        } catch (error) {
            showMessage(error.message, 'danger');
        } finally {
            button.disabled = false;
        }
    });
})();
