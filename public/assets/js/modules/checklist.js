(() => {
    'use strict';

    document.querySelectorAll('[data-module="checklist-owner"] select[name="estado"]').forEach((select) => {
        select.addEventListener('change', () => {
            select.classList.toggle('border-success', select.value === 'aprobado');
            select.classList.toggle('border-danger', select.value === 'fallido');
        });
    });
})();
