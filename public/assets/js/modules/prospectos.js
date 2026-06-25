(() => {
    'use strict';

    document.addEventListener('change', async (event) => {
        const select = event.target.closest('[data-prospect-status]');
        if (!select) return;
        try {
            await window.LegalOPS.request(select.dataset.prospectStatus, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ estado: select.value }),
            });
        } catch (error) {
            window.alert(error.message);
        }
    });
})();
