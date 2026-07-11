(() => {
    'use strict';

    const form = document.querySelector('[data-module="portal-autorizaciones"] form[data-ajax-form]');
    if (!form) return;

    const type = form.querySelector('[data-resource-type]');
    const resource = form.querySelector('[data-resource-id]');
    if (!type || !resource) return;

    const configs = {
        caso: { url: '/api/select/casos', parent: true },
        documento: { url: '/api/select/documentos', parent: true },
        honorario: { url: '/api/select/honorarios', parent: true },
        pago: { url: '/api/select/pagos', parent: true },
        gasto: { url: '/api/select/gastos', parent: true },
        usuario_cliente: { url: '/api/select/usuarios-externos', parent: false },
    };

    const sync = () => {
        const config = configs[type.value] || configs.caso;
        resource.dataset.url = config.url;
        resource.dataset.emptyLabel = 'Seleccione';
        if (config.parent) {
            resource.dataset.parentField = 'cliente_id';
            resource.dataset.parentParam = 'cliente_id';
        } else {
            delete resource.dataset.parentField;
            delete resource.dataset.parentParam;
        }
        resource.innerHTML = '<option value="">Seleccione</option>';
        resource.value = '';
        const pending = window.LegalOPSSelects?.reload(resource);
        pending?.catch(() => {
            resource.disabled = false;
        });
    };

    type.addEventListener('change', sync);
    sync();
})();
