-- @up
CREATE TABLE caso_partes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NOT NULL,
    tipo_parte VARCHAR(40) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    nombre_normalizado VARCHAR(180) NOT NULL,
    tipo_documento VARCHAR(40) NULL,
    numero_documento VARCHAR(80) NULL,
    documento_hash CHAR(64) NULL,
    email VARCHAR(254) NULL,
    telefono VARCHAR(60) NULL,
    direccion VARCHAR(255) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    observaciones TEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_caso_partes_id_firma (id, firma_id),
    KEY idx_caso_partes_caso (firma_id, caso_id, tipo_parte, deleted_at),
    KEY idx_caso_partes_nombre (firma_id, nombre_normalizado, deleted_at),
    KEY idx_caso_partes_documento (firma_id, documento_hash, deleted_at),
    CONSTRAINT fk_caso_partes_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_caso_partes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('partes.ver', 'partes', 'ver', 'Consultar partes procesales'),
('partes.crear', 'partes', 'crear', 'Crear partes procesales'),
('partes.editar', 'partes', 'editar', 'Editar partes procesales'),
('partes.eliminar', 'partes', 'eliminar', 'Eliminar partes procesales'),
('partes.revelar', 'partes', 'revelar', 'Revelar datos sensibles de partes')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('partes.ver','partes.crear','partes.editar','partes.eliminar','partes.revelar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('partes.ver','partes.crear','partes.editar','partes.eliminar','partes.revelar');

DELETE FROM permisos WHERE codigo IN ('partes.ver','partes.crear','partes.editar','partes.eliminar','partes.revelar');
DROP TABLE IF EXISTS caso_partes;
