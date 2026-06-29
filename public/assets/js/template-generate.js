(() => {
    'use strict';

    const state = { casoId: null, templates: [], selected: null, modal: null };

    async function openModal(casoId) {
        state.casoId = casoId;
        state.selected = null;
        const modalEl = document.getElementById('generateTemplateModal');
        if (!modalEl) return;
        if (!state.modal && window.bootstrap) state.modal = new window.bootstrap.Modal(modalEl);
        state.modal?.show();
        await loadTemplates();
    }

    async function loadTemplates(category = '') {
        const params = new URLSearchParams();
        if (category) params.set('categoria', category);
        const payload = await window.LegalOPS.request(`/plantillas/para-caso?${params.toString()}`);
        state.templates = payload.data;
        renderTemplateList();
        renderCategoryFilter();
        showStep(1);
    }

    function renderCategoryFilter() {
        const select = document.querySelector('[data-template-category-filter]');
        if (!select) return;
        const current = select.value;
        const categories = [...new Set(state.templates.map((item) => item.categoria).filter(Boolean))];
        select.innerHTML = '<option value="">Todas</option>' + categories.map((item) => `<option value="${escapeHtml(item)}">${escapeHtml(item)}</option>`).join('');
        select.value = current;
    }

    function renderTemplateList() {
        const list = document.querySelector('[data-template-list]');
        if (!list) return;
        list.innerHTML = state.templates.length === 0
            ? '<div class="text-secondary py-3">No hay plantillas activas.</div>'
            : state.templates.map((template) => `<button type="button" class="list-group-item list-group-item-action" data-select-template="${template.id}"><strong>${escapeHtml(template.nombre)}</strong><br><span class="small text-secondary">${escapeHtml(template.categoria || 'Sin categoria')}</span></button>`).join('');
    }

    function selectTemplate(templateId) {
        state.selected = state.templates.find((item) => String(item.id) === String(templateId)) ?? null;
        if (!state.selected) return;
        const used = state.selected.variables_usadas ?? [];
        document.querySelector('[data-template-used-vars]').innerHTML = used.length
            ? 'Variables: ' + used.map((item) => `<span class="badge text-bg-light border me-1">{{${escapeHtml(item)}}}</span>`).join('')
            : 'Esta plantilla no declara variables.';
        const custom = used.filter((item) => item.startsWith('custom.'));
        const fields = document.querySelector('[data-template-custom-fields]');
        fields.innerHTML = custom.length === 0
            ? '<p class="text-secondary">No requiere campos personalizados.</p>'
            : custom.map((item) => {
                const key = item.split('.')[1];
                const label = key.replace('campo_', 'Campo ');
                return `<div class="mb-3"><label class="form-label">${escapeHtml(label)}</label><input class="form-control" data-custom-var="${escapeHtml(key)}"></div>`;
            }).join('');
        showStep(2);
        document.querySelector('[data-template-generate]').disabled = false;
    }

    async function generate(templateId, casoId, customVars) {
        const payload = await window.LegalOPS.request(`/plantillas/${templateId}/generar`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ caso_id: casoId, custom_vars: customVars }),
        });
        const success = document.querySelector('[data-template-generate-success]');
        success.hidden = false;
        success.innerHTML = `Documento generado: <a href="${payload.data.url_descarga}" class="alert-link">${escapeHtml(payload.data.nombre)}</a> <a href="/documentos/${payload.data.documento_id}" class="btn btn-sm btn-success ms-2">Ir al documento</a>`;
    }

    function showStep(step) {
        document.querySelector('[data-step="1"]').hidden = step !== 1;
        document.querySelector('[data-step="2"]').hidden = step !== 2;
        document.querySelector('[data-template-back]').hidden = step === 1;
    }

    function customVars() {
        const vars = {};
        document.querySelectorAll('[data-custom-var]').forEach((input) => {
            vars[input.dataset.customVar] = input.value;
        });
        return vars;
    }

    function showError(error) {
        const box = document.querySelector('[data-template-generate-error]');
        if (!box) return;
        box.hidden = false;
        box.textContent = error.message || 'No fue posible generar el documento.';
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    }

    document.addEventListener('click', (event) => {
        const open = event.target.closest('[data-open-generate-template]');
        if (open) openModal(open.dataset.openGenerateTemplate).catch(showError);
        const select = event.target.closest('[data-select-template]');
        if (select) selectTemplate(select.dataset.selectTemplate);
        const back = event.target.closest('[data-template-back]');
        if (back) showStep(1);
        const generateButton = event.target.closest('[data-template-generate]');
        if (generateButton && state.selected && state.casoId) {
            generateButton.disabled = true;
            generate(state.selected.id, state.casoId, customVars()).catch(showError).finally(() => { generateButton.disabled = false; });
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-template-category-filter]')) {
            loadTemplates(event.target.value).catch(showError);
        }
    });

    window.TemplateGenerate = { openModal, selectTemplate, generate };
})();
