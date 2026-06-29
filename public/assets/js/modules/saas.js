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

    const promptModal = (() => {
        let modalElement = null;
        let instance = null;

        const ensure = () => {
            if (modalElement) return modalElement;
            modalElement = document.createElement('div');
            modalElement.className = 'modal fade';
            modalElement.tabIndex = -1;
            modalElement.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" data-prompt-title></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <textarea class="form-control" rows="7" maxlength="4000" data-prompt-value></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" data-prompt-confirm>Aceptar</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(modalElement);
            instance = new bootstrap.Modal(modalElement);
            return modalElement;
        };

        return (title) => new Promise((resolve) => {
            const element = ensure();
            const textarea = element.querySelector('[data-prompt-value]');
            const confirm = element.querySelector('[data-prompt-confirm]');
            element.querySelector('[data-prompt-title]').textContent = title;
            textarea.value = '';

            const cleanup = (value) => {
                confirm.removeEventListener('click', accept);
                element.removeEventListener('hidden.bs.modal', cancel);
                resolve(value);
            };
            const accept = () => {
                const value = textarea.value;
                instance.hide();
                cleanup(value);
            };
            const cancel = () => cleanup(null);

            confirm.addEventListener('click', accept, { once: true });
            element.addEventListener('hidden.bs.modal', cancel, { once: true });
            instance.show();
            window.setTimeout(() => textarea.focus(), 150);
        });
    })();

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
                const value = await promptModal(button.dataset.prompt);
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
