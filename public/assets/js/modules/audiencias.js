(() => {
    'use strict';

    const palette = {
        realizada: 'text-bg-success',
        cancelada: 'text-bg-secondary',
        pendiente_resultado: 'text-bg-warning',
        programada: 'text-bg-primary',
    };

    document.querySelectorAll('[data-hearing-badge]').forEach((badge) => {
        const state = badge.textContent.trim();
        badge.className = `badge ${palette[state] || 'text-bg-secondary'}`;
    });

    document.querySelectorAll('[data-hearing-state="pendiente_resultado"]').forEach((row) => {
        row.classList.add('table-warning');
    });
})();
