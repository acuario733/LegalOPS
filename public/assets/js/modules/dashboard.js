(() => {
    'use strict';

    const dashboard = document.querySelector('[data-module="dashboard"]');
    if (!dashboard) return;
    dashboard.querySelectorAll('.small-box').forEach((box) => box.classList.add('shadow-sm'));
})();
