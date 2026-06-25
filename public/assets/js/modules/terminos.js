(() => {
    'use strict';

    const palette = {
        cumplido: 'text-bg-success',
        vencido: 'text-bg-danger',
        critico: 'text-bg-warning',
        proximo: 'text-bg-info',
        vigente: 'text-bg-secondary',
    };

    document.querySelectorAll('[data-term-badge]').forEach((badge) => {
        const state = badge.textContent.trim();
        badge.className = `badge ${palette[state] || 'text-bg-secondary'}`;
    });

    document.querySelectorAll('[data-term-state="vencido"], [data-term-state="critico"]').forEach((row) => {
        row.classList.add(row.dataset.termState === 'vencido' ? 'table-danger' : 'table-warning');
    });
})();
