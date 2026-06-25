(() => {
    'use strict';

    document.querySelectorAll('[data-module="reportes"] a[href*="/exportar"]').forEach((link) => {
        link.addEventListener('click', () => link.classList.add('disabled'));
    });
})();
