<?php declare(strict_types=1); ?>
<div class="modal fade" id="comunicacionModal" tabindex="-1" aria-labelledby="comunicacionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="comunicacionModalLabel">Registrar comunicacion</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="comunicacion-form">
                    <ul class="nav nav-pills mb-3" role="tablist">
                        <?php foreach (['email' => 'Email', 'llamada' => 'Llamada', 'mensaje' => 'Mensaje', 'reunion' => 'Reunion', 'otro' => 'Otro'] as $tipo => $label): ?>
                            <li class="nav-item" role="presentation"><button class="nav-link <?= $tipo === 'email' ? 'active' : '' ?>" type="button" data-comunicacion-tipo="<?= e($tipo) ?>"><?= e($label) ?></button></li>
                        <?php endforeach; ?>
                    </ul>
                    <input type="hidden" name="tipo" id="comunicacion-tipo" value="email">
                    <div class="row g-3">
                        <div class="col-md-6" data-field="direccion">
                            <label class="form-label" for="comunicacion-direccion">Direccion</label>
                            <select id="comunicacion-direccion" name="direccion" class="form-select">
                                <option value="saliente">Saliente</option>
                                <option value="entrante">Entrante</option>
                                <option value="interno">Interno</option>
                            </select>
                        </div>
                        <div class="col-md-6" data-field="fecha">
                            <label class="form-label" for="comunicacion-fecha">Fecha y hora</label>
                            <input id="comunicacion-fecha" name="fecha_comunicacion" type="datetime-local" class="form-control" required>
                        </div>
                        <div class="col-12" data-field="asunto">
                            <label class="form-label" for="comunicacion-asunto">Asunto</label>
                            <input id="comunicacion-asunto" name="asunto" class="form-control" maxlength="500">
                        </div>
                        <div class="col-md-6" data-field="duracion" hidden>
                            <label class="form-label" for="comunicacion-duracion">Duracion (minutos)</label>
                            <input id="comunicacion-duracion" name="duracion_minutos" type="number" min="1" max="1440" class="form-control">
                        </div>
                        <div class="col-12" data-field="cuerpo">
                            <label class="form-label" for="comunicacion-cuerpo">Cuerpo / notas</label>
                            <textarea id="comunicacion-cuerpo" name="cuerpo" class="form-control" rows="5" maxlength="10000"></textarea>
                        </div>
                        <div class="col-12" data-field="participantes">
                            <label class="form-label" for="comunicacion-participantes">Participantes</label>
                            <input id="comunicacion-participantes" name="participantes" class="form-control" placeholder="cliente@correo.com, abogado@firma.com">
                            <div class="form-text">Separados por coma.</div>
                        </div>
                    </div>
                </form>
                <div class="alert alert-danger mt-3" id="comunicacion-error" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" data-submit-comunicacion>Guardar</button>
            </div>
        </div>
    </div>
</div>
