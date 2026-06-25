-- @up
CREATE TABLE terminos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    tarea_id BIGINT UNSIGNED NULL,
    responsable_usuario_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    titulo_normalizado VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    prioridad VARCHAR(30) NOT NULL DEFAULT 'media',
    estado VARCHAR(30) NOT NULL DEFAULT 'vigente',
    alerta_dias SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    cumplido_at DATETIME(6) NULL,
    cumplido_por_usuario_id BIGINT UNSIGNED NULL,
    observacion_cumplimiento VARCHAR(1000) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_terminos_id_firma (id, firma_id),
    KEY idx_terminos_estado_vencimiento (firma_id, estado, fecha_vencimiento, deleted_at),
    KEY idx_terminos_caso_vencimiento (firma_id, caso_id, fecha_vencimiento, deleted_at),
    KEY idx_terminos_tarea (firma_id, tarea_id, deleted_at),
    KEY idx_terminos_responsable (firma_id, responsable_usuario_id, fecha_vencimiento, deleted_at),
    KEY idx_terminos_titulo (firma_id, titulo_normalizado, deleted_at),
    CONSTRAINT fk_terminos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_terminos_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_terminos_responsable FOREIGN KEY (responsable_usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_terminos_cumplido_por FOREIGN KEY (cumplido_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('terminos.ver', 'terminos', 'ver', 'Consultar terminos juridicos'),
('terminos.crear', 'terminos', 'crear', 'Crear terminos juridicos'),
('terminos.editar', 'terminos', 'editar', 'Editar terminos juridicos'),
('terminos.cumplir', 'terminos', 'cumplir', 'Marcar terminos como cumplidos'),
('terminos.eliminar', 'terminos', 'eliminar', 'Eliminar terminos juridicos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('terminos.ver','terminos.crear','terminos.editar','terminos.cumplir','terminos.eliminar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('terminos.ver','terminos.crear','terminos.editar','terminos.cumplir','terminos.eliminar');

DELETE FROM permisos WHERE codigo IN ('terminos.ver','terminos.crear','terminos.editar','terminos.cumplir','terminos.eliminar');
DROP TABLE IF EXISTS terminos;
