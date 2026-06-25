(() => {
    'use strict';

    const palette = {
        completada: 'text-bg-success',
        cancelada: 'text-bg-secondary',
        vencida: 'text-bg-danger',
        en_proceso: 'text-bg-info',
        pendiente: 'text-bg-primary',
    };

    document.querySelectorAll('[data-task-badge]').forEach((badge) => {
        const state = badge.textContent.trim();
        badge.className = `badge ${palette[state] || 'text-bg-secondary'}`;
    });

    document.querySelectorAll('[data-task-state="vencida"]').forEach((row) => {
        row.classList.add('table-danger');
    });
})();
