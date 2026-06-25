(() => {
    'use strict';

    const showMessage = (message, type = 'success') => {
        const container = document.querySelector('[data-feedback]');
        if (!container) return;
        container.className = `alert alert-${type}`;
        container.textContent = message;
        container.hidden = false;
    };

    const formPayload = (form) => {
        const data = new FormData(form);
        return data;
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-ajax-form]');
        if (!form) return;
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button) button.disabled = true;
        try {
            const payload = await window.LegalOPS.request(form.action, {
                method: form.method || 'POST',
                body: formPayload(form),
            });
            showMessage(payload.message);
            if (form.dataset.reload !== 'false') window.location.reload();
        } catch (error) {
            showMessage(error.message, 'danger');
        } finally {
            if (button) button.disabled = false;
        }
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const confirmation = button.dataset.confirm;
        if (confirmation && !window.confirm(confirmation)) return;
        button.disabled = true;
        try {
            const body = {};
            if (button.dataset.prompt) {
                const value = window.prompt(button.dataset.prompt);
                if (value === null) return;
                body[button.dataset.promptField || 'motivo'] = value;
            }
            const payload = await window.LegalOPS.request(button.dataset.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            if (button.dataset.redirect) {
                window.location.assign(button.dataset.redirect);
                return;
            }
            showMessage(payload.message);
            window.location.reload();
        } catch (error) {
            showMessage(error.message, 'danger');
        } finally {
            button.disabled = false;
        }
    });
})();

