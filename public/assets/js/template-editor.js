(() => {
    'use strict';

    function initTinyMCE(selector) {
        if (!window.tinymce || !document.querySelector(selector)) return;
        window.tinymce.init({
            selector,
            height: 500,
            menubar: false,
            plugins: 'lists table link code',
            toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | table link | code',
            setup(editor) {
                editor.on('init input change keyup', detectVariables);
            },
        });
    }

    function insertVariable(varCode) {
        const code = `{{${varCode}}}`;
        if (window.tinymce?.activeEditor) {
            window.tinymce.activeEditor.insertContent(code);
            detectVariables();
            return;
        }
        const textarea = document.getElementById('template-content');
        if (textarea) textarea.value += code;
        detectVariables();
    }

    function content() {
        return window.tinymce?.activeEditor ? window.tinymce.activeEditor.getContent() : (document.getElementById('template-content')?.value ?? '');
    }

    function detectVariables() {
        const panel = document.getElementById('template-detected-vars');
        if (!panel) return;
        const found = [...new Set([...content().matchAll(/{{\s*([a-z][a-z0-9_]*\.[a-z][a-z0-9_]*)\s*}}/gi)].map((match) => match[1].toLowerCase()))];
        panel.innerHTML = found.length === 0
            ? '<span class="text-secondary small">Sin variables detectadas.</span>'
            : found.map((item) => `<span class="badge text-bg-light border me-1 mb-1">{{${escapeHtml(item)}}}</span>`).join('');
    }

    async function saveTemplate() {
        const form = document.getElementById('template-form');
        if (!form) return;
        if (window.tinymce?.activeEditor) {
            document.getElementById('template-content').value = window.tinymce.activeEditor.getContent();
        }
        const id = form.dataset.templateId;
        const data = Object.fromEntries(new FormData(form).entries());
        data.activo = document.getElementById('template-active')?.checked ? 1 : 0;
        const category = document.getElementById('template-category');
        if (category?.value !== '__new') data.categoria_nueva = '';
        await window.LegalOPS.request(id ? `/plantillas/${id}` : '/plantillas', {
            method: id ? 'PATCH' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        window.location.href = '/plantillas';
    }

    async function deleteTemplate(id) {
        if (!window.confirm('¿Eliminar esta plantilla?')) return;
        await window.LegalOPS.request(`/plantillas/${id}`, { method: 'DELETE' });
        window.location.reload();
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTinyMCE('#template-content');
        detectVariables();
    });

    document.addEventListener('submit', (event) => {
        if (event.target?.id !== 'template-form') return;
        event.preventDefault();
        saveTemplate().catch((error) => window.alert(error.message || 'No fue posible guardar la plantilla.'));
    });

    document.addEventListener('click', (event) => {
        const variable = event.target.closest('[data-template-variable]');
        if (variable) insertVariable(variable.dataset.templateVariable);
        const remove = event.target.closest('[data-delete-template]');
        if (remove) deleteTemplate(remove.dataset.deleteTemplate).catch((error) => window.alert(error.message));
    });

    document.addEventListener('change', (event) => {
        if (event.target?.id !== 'template-category') return;
        const input = document.getElementById('template-category-new');
        if (!input) return;
        input.disabled = event.target.value !== '__new';
        if (input.disabled) input.value = '';
    });

    window.TemplateEditor = { initTinyMCE, insertVariable, detectVariables, saveTemplate };
})();
