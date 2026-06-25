(() => {
    'use strict';

    const form = document.querySelector('[data-module="portal-autorizaciones"] form[data-ajax-form]');
    if (!form) return;

    const type = form.querySelector('[data-resource-type]');
    const resource = form.querySelector('[data-resource-id]');
    if (!type || !resource) return;

    const options = Array.from(resource.options);
    const sync = () => {
        let firstVisible = null;
        options.forEach((option) => {
            const visible = option.dataset.type === type.value;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && firstVisible === null) firstVisible = option;
        });
        if (!resource.selectedOptions[0] || resource.selectedOptions[0].disabled) {
            resource.value = firstVisible ? firstVisible.value : '';
        }
    };

    type.addEventListener('change', sync);
    sync();
})();
