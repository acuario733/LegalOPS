(() => {
    'use strict';

    const debounce = (callback, wait = 250) => {
        let timer = 0;
        return (...args) => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback(...args), wait);
        };
    };

    const option = (value, label, selected = false) => {
        const element = document.createElement('option');
        element.value = value;
        element.textContent = label;
        element.selected = selected;
        return element;
    };

    const valueInForm = (select, field) => {
        if (!field) return '';
        const form = select.closest('form') || document;
        return form.querySelector(`[name="${CSS.escape(field)}"]`)?.value || '';
    };

    const load = async (select, query = '') => {
        if (!select.dataset.url) return;
        const parentField = select.dataset.parentField || '';
        const parentParam = select.dataset.parentParam || parentField;
        const parentValue = valueInForm(select, parentField);
        if (parentField && parentValue === '') {
            select.innerHTML = '';
            select.append(option('', select.dataset.emptyLabel || 'Seleccione', true));
            select.disabled = true;
            return;
        }

        const url = new URL(select.dataset.url, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('limit', select.dataset.limit || '20');
        if (parentField) {
            url.searchParams.set(parentParam, parentValue);
        }
        if (select.dataset.extraQuery) {
            for (const pair of select.dataset.extraQuery.split('&')) {
                const [key, value] = pair.split('=');
                if (key) url.searchParams.set(key, value || '');
            }
        }

        select.disabled = true;
        const selectedValue = select.value;
        const payload = await window.LegalOPS.request(url.toString());
        select.innerHTML = '';
        select.append(option('', select.dataset.emptyLabel || 'Seleccione', selectedValue === ''));
        for (const item of payload.data || []) {
            const itemOption = option(String(item.value), item.label || String(item.value), String(item.value) === selectedValue);
            Object.entries(item).forEach(([key, value]) => {
                if (['value', 'label'].includes(key) || value === null || value === undefined) return;
                itemOption.dataset[key.replace(/_/g, '-')] = String(value);
            });
            select.append(itemOption);
        }
        select.disabled = false;
    };

    const enhance = (select) => {
        if (select.dataset.ajaxEnhanced === '1') return;
        select.dataset.ajaxEnhanced = '1';

        const input = document.createElement('input');
        input.type = 'search';
        input.className = 'form-control form-control-sm mb-1';
        input.placeholder = select.dataset.placeholder || 'Buscar...';
        input.autocomplete = 'off';
        select.parentNode?.insertBefore(input, select);

        const debouncedLoad = debounce(() => {
            load(select, input.value).catch(() => {
                select.disabled = false;
            });
        });

        input.addEventListener('input', debouncedLoad);
        input.addEventListener('focus', () => {
            if (select.options.length <= 1) debouncedLoad();
        });

        const parentField = select.dataset.parentField || '';
        if (parentField) {
            const form = select.closest('form') || document;
            const parent = form.querySelector(`[name="${CSS.escape(parentField)}"]`);
            parent?.addEventListener('change', () => {
                input.value = '';
                select.value = '';
                load(select).catch(() => {
                    select.disabled = false;
                });
            });
            if (!parent?.value) {
                select.disabled = true;
            }
        }
    };

    window.LegalOPSSelects = {
        reload: (select, query = '') => load(select, query),
    };

    document.querySelectorAll('select[data-ajax-select]').forEach(enhance);
})();
