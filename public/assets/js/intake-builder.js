(() => {
    'use strict';

    class IntakeBuilder {
        constructor(root, initialFields = []) {
            this.root = root;
            this.canvas = root.querySelector('#intake-canvas');
            this.preview = root.querySelector('#intake-live-preview');
            this.properties = root.querySelector('#intake-properties');
            this.emptyProperties = root.querySelector('#intake-empty-properties');
            this.selectedIndex = null;
            this.fields = [];
            this.render(initialFields.length ? initialFields : this.defaultSpecialFields());
            this.bind();
            this.initSortable();
        }

        addField(type) {
            const labels = {
                texto: 'Campo de texto',
                email: 'Correo electrónico',
                telefono: 'Teléfono',
                select: 'Lista de opciones',
                textarea: 'Texto largo',
                fecha: 'Fecha',
                checkbox: 'Casilla de verificación',
            };
            const sequence = this.fields.length + 1;
            this.fields.push({
                key: `campo_${sequence}`,
                type,
                etiqueta: labels[type] || 'Nuevo campo',
                placeholder: '',
                requerido: false,
                opciones: type === 'select' ? ['Opción 1', 'Opción 2'] : [],
                especial: null,
                fijo: false,
            });
            this.render(this.fields);
            this.selectField(this.fields.length - 1);
        }

        removeField(index) {
            if (!this.fields[index] || this.fields[index].fijo) return;
            this.fields.splice(index, 1);
            this.selectedIndex = null;
            this.render(this.fields);
            this.showProperties(null);
        }

        selectField(index) {
            if (!this.fields[index]) return;
            this.selectedIndex = index;
            this.renderCanvas();
            this.showProperties(this.fields[index]);
        }

        updateField(index, props) {
            if (!this.fields[index]) return;
            this.fields[index] = {...this.fields[index], ...props};
            this.renderCanvas();
            this.renderPreview();
        }

        serialize() {
            return this.fields.map(field => ({
                key: field.key,
                type: field.type,
                etiqueta: field.etiqueta,
                placeholder: field.placeholder || '',
                requerido: Boolean(field.requerido),
                opciones: Array.isArray(field.opciones) ? field.opciones : [],
                especial: field.especial || null,
                fijo: Boolean(field.fijo),
            }));
        }

        render(fields) {
            this.fields = Array.isArray(fields) ? fields.map(field => ({...field})) : [];
            this.renderCanvas();
            this.renderPreview();
        }

        bind() {
            this.root.querySelectorAll('[data-add-field]').forEach(button => {
                button.addEventListener('click', () => this.addField(button.dataset.addField));
            });
            this.canvas.addEventListener('click', event => {
                const remove = event.target.closest('[data-remove-field]');
                if (remove) {
                    this.removeField(Number(remove.dataset.removeField));
                    return;
                }
                const row = event.target.closest('[data-field-index]');
                if (row) this.selectField(Number(row.dataset.fieldIndex));
            });

            const label = this.root.querySelector('#field-label');
            const placeholder = this.root.querySelector('#field-placeholder');
            const required = this.root.querySelector('#field-required');
            const options = this.root.querySelector('#field-options');
            label.addEventListener('input', () => this.updateSelected({etiqueta: label.value}));
            placeholder.addEventListener('input', () => this.updateSelected({placeholder: placeholder.value}));
            required.addEventListener('change', () => this.updateSelected({requerido: required.checked}));
            options.addEventListener('input', () => this.updateSelected({
                opciones: options.value.split('\n').map(value => value.trim()).filter(Boolean),
            }));

            document.getElementById('save-intake')?.addEventListener('click', () => this.save());
        }

        initSortable() {
            if (typeof window.Sortable !== 'function') return;
            window.Sortable.create(this.canvas, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: event => {
                    if (event.oldIndex === event.newIndex) return;
                    const [field] = this.fields.splice(event.oldIndex, 1);
                    this.fields.splice(event.newIndex, 0, field);
                    this.selectedIndex = event.newIndex;
                    this.render(this.fields);
                },
            });
        }

        renderCanvas() {
            this.canvas.replaceChildren();
            this.fields.forEach((field, index) => {
                const row = document.createElement('div');
                row.className = `builder-field${this.selectedIndex === index ? ' is-selected' : ''}`;
                row.dataset.fieldIndex = String(index);

                const handle = document.createElement('span');
                handle.className = 'drag-handle';
                handle.setAttribute('aria-label', 'Reordenar campo');
                handle.textContent = '⠿';

                const label = document.createElement('div');
                label.className = 'flex-grow-1';
                const strong = document.createElement('strong');
                strong.textContent = field.etiqueta;
                const meta = document.createElement('div');
                meta.className = 'small text-secondary';
                meta.textContent = `${field.type}${field.requerido ? ' · obligatorio' : ''}${field.fijo ? ' · fijo' : ''}`;
                label.append(strong, meta);

                row.append(handle, label);
                if (!field.fijo) {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'btn btn-sm btn-outline-danger';
                    remove.dataset.removeField = String(index);
                    remove.setAttribute('aria-label', `Eliminar ${field.etiqueta}`);
                    remove.textContent = 'Eliminar';
                    row.append(remove);
                }
                this.canvas.append(row);
            });
        }

        renderPreview() {
            this.preview.replaceChildren();
            this.fields.slice(0, 5).forEach(field => {
                const group = document.createElement('div');
                group.className = 'mb-2';
                const label = document.createElement('label');
                label.className = 'form-label small';
                label.textContent = `${field.etiqueta}${field.requerido ? ' *' : ''}`;
                let control;
                if (field.type === 'textarea') {
                    control = document.createElement('textarea');
                    control.rows = 2;
                } else if (field.type === 'select') {
                    control = document.createElement('select');
                    field.opciones.forEach(option => {
                        const item = document.createElement('option');
                        item.textContent = option;
                        control.append(item);
                    });
                } else {
                    control = document.createElement('input');
                    control.type = field.type === 'email' ? 'email' : field.type === 'fecha' ? 'date' : 'text';
                }
                control.className = 'form-control form-control-sm';
                control.disabled = true;
                group.append(label, control);
                this.preview.append(group);
            });
        }

        showProperties(field) {
            this.properties.hidden = !field;
            this.emptyProperties.hidden = Boolean(field);
            if (!field) return;
            this.root.querySelector('#field-label').value = field.etiqueta || '';
            this.root.querySelector('#field-placeholder').value = field.placeholder || '';
            const required = this.root.querySelector('#field-required');
            required.checked = Boolean(field.requerido);
            required.disabled = field.especial === 'nombre' || field.especial === 'email';
            const optionsWrap = this.root.querySelector('#field-options-wrap');
            optionsWrap.hidden = field.type !== 'select';
            this.root.querySelector('#field-options').value = (field.opciones || []).join('\n');
        }

        updateSelected(props) {
            if (this.selectedIndex === null) return;
            this.updateField(this.selectedIndex, props);
        }

        async save() {
            const payload = {
                nombre: document.getElementById('form-name').value,
                titulo: document.getElementById('form-title').value,
                descripcion: document.getElementById('form-description').value,
                mensaje_exito: document.getElementById('form-success').value,
                notificar_emails: document.getElementById('form-notify').value,
                activo: true,
                campos: this.serialize(),
            };
            const id = this.root.dataset.formId;
            try {
                const response = await window.LegalOPS.request(id ? `/intake/${id}` : '/intake', {
                    method: id ? 'PATCH' : 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload),
                });
                window.location.href = `/intake/${response.data.id}/editar`;
            } catch (error) {
                window.alert(error.message);
            }
        }

        defaultSpecialFields() {
            return [
                {key: 'nombre', type: 'texto', etiqueta: 'Nombre completo', placeholder: '', requerido: true, opciones: [], especial: 'nombre', fijo: true},
                {key: 'email', type: 'email', etiqueta: 'Correo electrónico', placeholder: '', requerido: true, opciones: [], especial: 'email', fijo: true},
                {key: 'telefono', type: 'telefono', etiqueta: 'Teléfono', placeholder: '', requerido: false, opciones: [], especial: 'telefono', fijo: true},
            ];
        }
    }

    window.IntakeBuilder = IntakeBuilder;
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('intake-builder');
        if (!root) return;
        let fields = [];
        try {
            fields = JSON.parse(root.dataset.fields || '[]');
        } catch {
            fields = [];
        }
        new IntakeBuilder(root, fields);
    });
})();
