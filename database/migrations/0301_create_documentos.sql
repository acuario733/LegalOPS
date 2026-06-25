-- @up
CREATE TABLE documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NULL,
    caso_id BIGINT UNSIGNED NULL,
    gasto_id BIGINT UNSIGNED NULL,
    current_version_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    titulo_normalizado VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    tipo_documental VARCHAR(80) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    visible_portal TINYINT(1) NOT NULL DEFAULT 0,
    created_by_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_documentos_id_firma (id, firma_id),
    KEY idx_documentos_cliente (firma_id, cliente_id, deleted_at),
    KEY idx_documentos_caso (firma_id, caso_id, deleted_at),
    KEY idx_documentos_gasto (firma_id, gasto_id, deleted_at),
    KEY idx_documentos_titulo (firma_id, titulo_normalizado, deleted_at),
    KEY idx_documentos_current_version (current_version_id),
    KEY idx_documentos_creador (created_by_usuario_id),
    CONSTRAINT fk_documentos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_documentos_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_documentos_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_documentos_creador FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('documentos.ver', 'documentos', 'ver', 'Consultar documentos'),
('documentos.cargar', 'documentos', 'cargar', 'Cargar documentos privados'),
('documentos.editar', 'documentos', 'editar', 'Editar metadata de documentos'),
('documentos.eliminar', 'documentos', 'eliminar', 'Eliminar documentos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('documentos.ver','documentos.cargar','documentos.editar','documentos.eliminar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('documentos.ver','documentos.cargar','documentos.editar','documentos.eliminar');

DELETE FROM permisos WHERE codigo IN ('documentos.ver','documentos.cargar','documentos.editar','documentos.eliminar');
DROP TABLE IF EXISTS documentos;
