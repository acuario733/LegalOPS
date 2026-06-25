(() => {
    'use strict';

    const feedback = (message, type = 'success') => {
        const container = document.querySelector('[data-feedback]');
        if (!container) return;
        container.className = `alert alert-${type}`;
        container.textContent = message;
        container.hidden = false;
    };

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-party-reveal]');
        if (!button) return;
        button.disabled = true;
        try {
            const payload = await window.LegalOPS.request(button.dataset.partyReveal, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ campo: button.dataset.field }),
            });
            const target = document.querySelector(`[data-party-target="${button.dataset.target}"]`);
            if (target) target.textContent = payload.data.valor || '';
            feedback(payload.message);
        } catch (error) {
            feedback(error.message, 'danger');
        } finally {
            button.disabled = false;
        }
    });
})();
