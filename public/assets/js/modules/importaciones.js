(() => {
    'use strict';

    document.querySelectorAll('[data-module="importaciones"] input[type="file"]').forEach((input) => {
        input.addEventListener('change', () => {
            const form = input.closest('form');
            if (form) form.dataset.reload = 'true';
        });
    });
})();
