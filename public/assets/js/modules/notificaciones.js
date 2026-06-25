(() => {
    'use strict';

    document.querySelectorAll('[data-notification-state="pendiente"]').forEach((row) => {
        row.classList.add('table-warning');
    });
})();
