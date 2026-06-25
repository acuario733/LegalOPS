-- @up
CREATE TABLE prospectos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    nombre_normalizado VARCHAR(180) NOT NULL,
    tipo_persona VARCHAR(20) NOT NULL DEFAULT 'natural',
    email VARCHAR(254) NULL,
    telefono VARCHAR(60) NULL,
    tipo_documento VARCHAR(40) NULL,
    numero_documento VARCHAR(80) NULL,
    documento_normalizado VARCHAR(80) NULL,
    documento_hash CHAR(64) NULL,
    empresa VARCHAR(180) NULL,
    empresa_normalizada VARCHAR(180) NULL,
    fuente VARCHAR(120) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'nuevo',
    responsable_usuario_id BIGINT UNSIGNED NULL,
    valor_estimado DECIMAL(14,2) NULL,
    notas TEXT NULL,
    tratamiento_datos_autorizado TINYINT(1) NOT NULL DEFAULT 0,
    converted_cliente_id BIGINT UNSIGNED NULL,
    converted_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prospectos_id_firma (id, firma_id),
    UNIQUE KEY uq_prospectos_cliente_convertido (firma_id, converted_cliente_id),
    KEY idx_prospectos_pipeline (firma_id, estado, deleted_at, updated_at),
    KEY idx_prospectos_nombre (firma_id, nombre_normalizado, deleted_at),
    KEY idx_prospectos_documento (firma_id, documento_hash, deleted_at),
    KEY idx_prospectos_responsable (firma_id, responsable_usuario_id, estado),
    CONSTRAINT fk_prospectos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_prospectos_responsable FOREIGN KEY (responsable_usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_prospectos_cliente FOREIGN KEY (converted_cliente_id, firma_id) REFERENCES clientes (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('prospectos.ver', 'prospectos', 'ver', 'Consultar prospectos'),
('prospectos.crear', 'prospectos', 'crear', 'Crear prospectos'),
('prospectos.editar', 'prospectos', 'editar', 'Editar prospectos'),
('prospectos.convertir', 'prospectos', 'convertir', 'Convertir prospectos en clientes')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('prospectos.ver','prospectos.crear','prospectos.editar','prospectos.convertir')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('prospectos.ver','prospectos.crear','prospectos.editar','prospectos.convertir');

DELETE FROM permisos WHERE codigo IN ('prospectos.ver','prospectos.crear','prospectos.editar','prospectos.convertir');
DROP TABLE IF EXISTS prospectos;
