-- @up
CREATE TABLE document_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    categoria VARCHAR(100) NULL,
    contenido LONGTEXT NOT NULL,
    variables_usadas JSON NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_templates_id_firma (id, firma_id),
    KEY idx_document_templates_firma (firma_id),
    KEY idx_document_templates_categoria (firma_id, categoria),
    KEY idx_document_templates_activo (firma_id, activo),
    CONSTRAINT fk_document_templates_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('plantillas.ver', 'plantillas', 'ver', 'Consultar plantillas documentales'),
('plantillas.crear', 'plantillas', 'crear', 'Crear plantillas documentales'),
('plantillas.editar', 'plantillas', 'editar', 'Editar plantillas documentales'),
('plantillas.eliminar', 'plantillas', 'eliminar', 'Eliminar plantillas documentales'),
('plantillas.usar', 'plantillas', 'usar', 'Generar documentos desde plantillas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('plantillas.ver', 'plantillas.crear', 'plantillas.editar', 'plantillas.eliminar', 'plantillas.usar')
WHERE r.codigo = 'administrador'
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('plantillas.ver', 'plantillas.crear', 'plantillas.editar', 'plantillas.usar')
WHERE r.codigo = 'abogado'
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('plantillas.ver', 'plantillas.usar')
WHERE r.codigo = 'asistente'
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('plantillas.ver', 'plantillas.crear', 'plantillas.editar', 'plantillas.eliminar', 'plantillas.usar');

DELETE FROM permisos WHERE codigo IN ('plantillas.ver', 'plantillas.crear', 'plantillas.editar', 'plantillas.eliminar', 'plantillas.usar');
DROP TABLE IF EXISTS document_templates;
