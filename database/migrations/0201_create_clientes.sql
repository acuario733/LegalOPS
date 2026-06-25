-- @up
CREATE TABLE clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    tipo_persona VARCHAR(20) NOT NULL DEFAULT 'natural',
    nombre_razon_social VARCHAR(180) NOT NULL,
    nombre_normalizado VARCHAR(180) NOT NULL,
    tipo_documento VARCHAR(40) NULL,
    numero_documento VARCHAR(80) NULL,
    documento_normalizado VARCHAR(80) NULL,
    documento_hash CHAR(64) NULL,
    email VARCHAR(254) NULL,
    telefono VARCHAR(60) NULL,
    direccion VARCHAR(255) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    origen VARCHAR(120) NULL,
    observaciones TEXT NULL,
    tratamiento_datos_autorizado TINYINT(1) NOT NULL DEFAULT 0,
    autorizacion_tratamiento_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clientes_id_firma (id, firma_id),
    KEY idx_clientes_firma_estado (firma_id, estado, deleted_at),
    KEY idx_clientes_firma_nombre (firma_id, nombre_normalizado, deleted_at),
    KEY idx_clientes_firma_documento (firma_id, documento_hash, deleted_at),
    KEY idx_clientes_documento_normalizado (firma_id, documento_normalizado, deleted_at),
    CONSTRAINT fk_clientes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_autorizaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(80) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'otorgada',
    medio VARCHAR(120) NULL,
    version_texto VARCHAR(80) NULL,
    evidencia_hash CHAR(64) NOT NULL,
    observacion VARCHAR(500) NULL,
    registrado_por_usuario_id BIGINT UNSIGNED NULL,
    revoked_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_cliente_autorizaciones_cliente (firma_id, cliente_id, tipo, created_at),
    KEY idx_cliente_autorizaciones_usuario (registrado_por_usuario_id),
    CONSTRAINT fk_cliente_autorizaciones_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_cliente_autorizaciones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_cliente_autorizaciones_usuario FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('clientes.ver', 'clientes', 'ver', 'Consultar clientes'),
('clientes.crear', 'clientes', 'crear', 'Crear clientes'),
('clientes.editar', 'clientes', 'editar', 'Editar clientes'),
('clientes.eliminar', 'clientes', 'eliminar', 'Eliminar clientes'),
('clientes.revelar', 'clientes', 'revelar', 'Revelar datos sensibles de clientes')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('clientes.ver','clientes.crear','clientes.editar','clientes.eliminar','clientes.revelar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('clientes.ver','clientes.crear','clientes.editar','clientes.eliminar','clientes.revelar');

DELETE FROM permisos WHERE codigo IN ('clientes.ver','clientes.crear','clientes.editar','clientes.eliminar','clientes.revelar');

DROP TABLE IF EXISTS cliente_autorizaciones;
DROP TABLE IF EXISTS clientes;
