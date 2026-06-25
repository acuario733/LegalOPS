(() => {
    'use strict';

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href*="/casos/"]');
        if (!link || !link.closest('.list-group')) return;
        link.classList.add('active');
    });
})();
