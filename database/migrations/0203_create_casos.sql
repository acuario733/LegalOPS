-- @up
CREATE TABLE casos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    responsable_usuario_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    titulo_normalizado VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    prioridad VARCHAR(30) NOT NULL DEFAULT 'media',
    tipo_proceso VARCHAR(120) NULL,
    jurisdiccion VARCHAR(120) NULL,
    despacho VARCHAR(180) NULL,
    radicado VARCHAR(120) NULL,
    fecha_apertura DATE NULL,
    closed_at DATETIME(6) NULL,
    closed_by_usuario_id BIGINT UNSIGNED NULL,
    close_reason VARCHAR(500) NULL,
    archived_at DATETIME(6) NULL,
    archived_by_usuario_id BIGINT UNSIGNED NULL,
    archive_reason VARCHAR(500) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_casos_id_firma (id, firma_id),
    UNIQUE KEY uq_casos_firma_radicado (firma_id, radicado),
    KEY idx_casos_cliente_estado (firma_id, cliente_id, estado, deleted_at),
    KEY idx_casos_responsable_estado (firma_id, responsable_usuario_id, estado, deleted_at),
    KEY idx_casos_titulo (firma_id, titulo_normalizado, deleted_at),
    KEY idx_casos_estado_prioridad (firma_id, estado, prioridad, deleted_at),
    CONSTRAINT fk_casos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_casos_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_casos_responsable FOREIGN KEY (responsable_usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_casos_cerrado_por FOREIGN KEY (closed_by_usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_casos_archivado_por FOREIGN KEY (archived_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('casos.ver', 'casos', 'ver', 'Consultar casos'),
('casos.crear', 'casos', 'crear', 'Crear casos'),
('casos.editar', 'casos', 'editar', 'Editar casos'),
('casos.cerrar', 'casos', 'cerrar', 'Cerrar casos'),
('casos.archivar', 'casos', 'archivar', 'Archivar casos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('casos.ver','casos.crear','casos.editar','casos.cerrar','casos.archivar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('casos.ver','casos.crear','casos.editar','casos.cerrar','casos.archivar');

DELETE FROM permisos WHERE codigo IN ('casos.ver','casos.crear','casos.editar','casos.cerrar','casos.archivar');
DROP TABLE IF EXISTS casos;
