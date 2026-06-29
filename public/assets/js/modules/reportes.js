(() => {
    'use strict';

    document.querySelectorAll('[data-module="reportes"] a[href*="/exportar"]').forEach((link) => {
        link.addEventListener('click', () => link.classList.add('disabled'));
    });

    document.querySelectorAll('[data-report-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
        });
    });
})();
