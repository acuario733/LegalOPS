<?php declare(strict_types=1); ?>
<div class="modal fade" id="generateTemplateModal" tabindex="-1" aria-labelledby="generateTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="generateTemplateModalLabel">Generar desde plantilla</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" data-template-generate-body>
                <div data-step="1">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                        <strong>1. Seleccionar plantilla</strong>
                        <select class="form-select form-select-sm w-auto" data-template-category-filter><option value="">Todas</option></select>
                    </div>
                    <div class="list-group" data-template-list></div>
                </div>
                <div data-step="2" hidden>
                    <strong>2. Campos personalizados</strong>
                    <div class="text-secondary small mb-3" data-template-used-vars></div>
                    <div data-template-custom-fields></div>
                </div>
                <div class="alert alert-danger mt-3" data-template-generate-error hidden></div>
                <div class="alert alert-success mt-3" data-template-generate-success hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-template-back hidden>Atras</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" data-template-generate disabled>Generar documento</button>
            </div>
        </div>
    </div>
</div>
