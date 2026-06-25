(() => {
    'use strict';

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    async function request(url, options = {}) {
        const method = String(options.method ?? 'GET').toUpperCase();
        const headers = new Headers(options.headers ?? {});
        headers.set('Accept', 'application/json');
        headers.set('X-Requested-With', 'XMLHttpRequest');

        if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            headers.set('X-CSRF-TOKEN', csrfToken());
        }

        const response = await fetch(url, {
            ...options,
            method,
            headers,
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({
            ok: false,
            message: 'La respuesta del servidor no tiene un formato válido.',
            data: {},
            errors: {},
        }));

        if (!response.ok) {
            const error = new Error(payload.message || 'No fue posible completar la solicitud.');
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload;
    }

    window.LegalOPS = Object.freeze({ request });
})();

