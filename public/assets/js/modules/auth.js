(() => {
    'use strict';

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-auth-form]');
        if (!form) return;
        event.preventDefault();
        const feedback = form.querySelector('[data-auth-feedback]');
        const button = form.querySelector('[type="submit"]');
        if (button) button.disabled = true;
        try {
            const payload = await window.LegalOPS.request(form.action, { method: 'POST', body: new FormData(form) });
            if (feedback) {
                feedback.className = 'alert alert-success';
                feedback.textContent = payload.message;
                feedback.hidden = false;
            }
            if (payload.data?.redirect) window.location.assign(payload.data.redirect);
        } catch (error) {
            if (feedback) {
                feedback.className = 'alert alert-danger';
                feedback.textContent = error.message;
                feedback.hidden = false;
            }
        } finally {
            if (button) button.disabled = false;
        }
    });
})();

