(() => {
    'use strict';

    document.querySelectorAll('input[type="file"][name="archivo"]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            const label = input.closest('form')?.querySelector('[data-file-size]');
            if (label) label.textContent = `${(file.size / 1024).toFixed(1)} KB`;
        });
    });
})();
