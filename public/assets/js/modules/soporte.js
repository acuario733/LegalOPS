(() => {
    'use strict';

    document.querySelectorAll('[data-module^="soporte"] textarea[name="mensaje"]').forEach((textarea) => {
        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.min(textarea.scrollHeight, 260)}px`;
        });
    });
})();
