-- @up
CREATE TABLE tareas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    termino_id BIGINT UNSIGNED NULL,
    responsable_usuario_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    titulo_normalizado VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    prioridad VARCHAR(30) NOT NULL DEFAULT 'media',
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    fecha_vencimiento DATE NULL,
    completed_at DATETIME(6) NULL,
    completed_by_usuario_id BIGINT UNSIGNED NULL,
    reassigned_at DATETIME(6) NULL,
    reassigned_from_usuario_id BIGINT UNSIGNED NULL,
    reassigned_to_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tareas_id_firma (id, firma_id),
    KEY idx_tareas_estado_vencimiento (firma_id, estado, fecha_vencimiento, deleted_at),
    KEY idx_tareas_caso_estado (firma_id, caso_id, estado, deleted_at),
    KEY idx_tareas_termino (firma_id, termino_id, deleted_at),
    KEY idx_tareas_responsable (firma_id, responsable_usuario_id, estado, deleted_at),
    KEY idx_tareas_titulo (firma_id, titulo_normalizado, deleted_at),
    CONSTRAINT fk_tareas_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_tareas_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_tareas_termino FOREIGN KEY (termino_id, firma_id) REFERENCES terminos (id, firma_id),
    CONSTRAINT fk_tareas_responsable FOREIGN KEY (responsable_usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_tareas_completed_by FOREIGN KEY (completed_by_usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_tareas_reassigned_from FOREIGN KEY (reassigned_from_usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_tareas_reassigned_to FOREIGN KEY (reassigned_to_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE terminos
    ADD CONSTRAINT fk_terminos_tarea FOREIGN KEY (tarea_id, firma_id) REFERENCES tareas (id, firma_id);

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('tareas.ver', 'tareas', 'ver', 'Consultar tareas'),
('tareas.crear', 'tareas', 'crear', 'Crear tareas'),
('tareas.editar', 'tareas', 'editar', 'Editar tareas'),
('tareas.reasignar', 'tareas', 'reasignar', 'Reasignar tareas'),
('tareas.cambiar_estado', 'tareas', 'cambiar_estado', 'Cambiar estado de tareas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('tareas.ver','tareas.crear','tareas.editar','tareas.reasignar','tareas.cambiar_estado')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
ALTER TABLE terminos DROP FOREIGN KEY fk_terminos_tarea;

DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('tareas.ver','tareas.crear','tareas.editar','tareas.reasignar','tareas.cambiar_estado');

DELETE FROM permisos WHERE codigo IN ('tareas.ver','tareas.crear','tareas.editar','tareas.reasignar','tareas.cambiar_estado');
DROP TABLE IF EXISTS tareas;
