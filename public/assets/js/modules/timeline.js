(() => {
    'use strict';

    document.addEventListener('change', (event) => {
        const select = event.target.closest('select[name="visibilidad"]');
        if (!select || select.value !== 'publica') return;
        const form = select.closest('form');
        const publicText = form?.querySelector('[name="contenido_publico"]');
        if (publicText && publicText.value.trim() === '') {
            publicText.focus();
        }
    });
})();
