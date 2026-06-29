'use strict';

/**
 * Inicialización de componentes cuando el DOM está listo.
 * Monta automáticamente todos los componentes registrados en la página.
 */
document.addEventListener('DOMContentLoaded', () => {
    // Formularios AJAX
    document.querySelectorAll('[data-ajax-form]').forEach(el => {
        const form = new FormComponent(el);
        form.mount();
    });

    // Tablas
    document.querySelectorAll('[data-table]').forEach(el => {
        const table = new TableComponent(el);
        table.mount();
    });

    // Modales
    document.querySelectorAll('[data-modal]').forEach(el => {
        const modal = new ModalComponent(el);
        modal.mount();
    });
});
