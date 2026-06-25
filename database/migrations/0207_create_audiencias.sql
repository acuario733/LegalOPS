-- @up
CREATE TABLE audiencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NOT NULL,
    responsable_usuario_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    modalidad VARCHAR(30) NOT NULL DEFAULT 'presencial',
    despacho VARCHAR(180) NULL,
    lugar VARCHAR(255) NULL,
    enlace VARCHAR(500) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'programada',
    resultado TEXT NULL,
    resultado_registrado_at DATETIME(6) NULL,
    resultado_registrado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_audiencias_id_firma (id, firma_id),
    KEY idx_audiencias_caso_fecha (firma_id, caso_id, fecha, hora, deleted_at),
    KEY idx_audiencias_estado_fecha (firma_id, estado, fecha, hora, deleted_at),
    KEY idx_audiencias_responsable (firma_id, responsable_usuario_id, fecha, deleted_at),
    CONSTRAINT fk_audiencias_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_audiencias_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_audiencias_responsable FOREIGN KEY (responsable_usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_audiencias_resultado_por FOREIGN KEY (resultado_registrado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('audiencias.ver', 'audiencias', 'ver', 'Consultar audiencias'),
('audiencias.crear', 'audiencias', 'crear', 'Crear audiencias'),
('audiencias.editar', 'audiencias', 'editar', 'Editar audiencias'),
('audiencias.registrar_resultado', 'audiencias', 'registrar_resultado', 'Registrar resultado de audiencia')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('audiencias.ver','audiencias.crear','audiencias.editar','audiencias.registrar_resultado')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('audiencias.ver','audiencias.crear','audiencias.editar','audiencias.registrar_resultado');

DELETE FROM permisos WHERE codigo IN ('audiencias.ver','audiencias.crear','audiencias.editar','audiencias.registrar_resultado');
DROP TABLE IF EXISTS audiencias;
