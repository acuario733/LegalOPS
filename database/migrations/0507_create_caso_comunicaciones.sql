-- @up
CREATE TABLE caso_comunicaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('email','llamada','mensaje','reunion','otro') NOT NULL,
    direccion ENUM('entrante','saliente','interno') NOT NULL DEFAULT 'saliente',
    asunto VARCHAR(500) NULL,
    cuerpo TEXT NULL,
    participantes JSON NULL,
    fecha_comunicacion TIMESTAMP NOT NULL,
    duracion_minutos INT UNSIGNED NULL,
    adjuntos JSON NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    origen ENUM('manual','email_bcc','sistema') NOT NULL DEFAULT 'manual',
    email_message_id VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_caso_comunicaciones_email_message (email_message_id),
    KEY idx_caso_comunicaciones_caso (firma_id, caso_id),
    KEY idx_caso_comunicaciones_tipo (firma_id, tipo),
    KEY idx_caso_comunicaciones_message (email_message_id),
    KEY idx_caso_comunicaciones_usuario (usuario_id, firma_id),
    CONSTRAINT fk_caso_comunicaciones_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_caso_comunicaciones_caso_firma
        FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_caso_comunicaciones_usuario_firma
        FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caso_email_addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    caso_id BIGINT UNSIGNED NOT NULL,
    firma_id BIGINT UNSIGNED NOT NULL,
    email_address VARCHAR(200) NOT NULL,
    token VARCHAR(32) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_caso_email_addresses_email (email_address),
    UNIQUE KEY uq_caso_email_addresses_token (token),
    UNIQUE KEY uq_caso_email_addresses_caso (firma_id, caso_id),
    KEY idx_caso_email_addresses_caso (caso_id, firma_id),
    KEY idx_caso_email_addresses_token (token),
    CONSTRAINT fk_caso_email_addresses_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_caso_email_addresses_caso_firma
        FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('comunicaciones.ver', 'comunicaciones', 'ver', 'Consultar comunicaciones de casos'),
('comunicaciones.crear', 'comunicaciones', 'crear', 'Registrar comunicaciones de casos'),
('comunicaciones.eliminar', 'comunicaciones', 'eliminar', 'Eliminar comunicaciones de casos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('comunicaciones.ver', 'comunicaciones.crear', 'comunicaciones.eliminar')
WHERE r.codigo IN ('administrador', 'abogado')
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('comunicaciones.ver', 'comunicaciones.crear')
WHERE r.codigo = 'asistente'
  AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('comunicaciones.ver', 'comunicaciones.crear', 'comunicaciones.eliminar');

DELETE FROM permisos WHERE codigo IN ('comunicaciones.ver', 'comunicaciones.crear', 'comunicaciones.eliminar');
DROP TABLE IF EXISTS caso_email_addresses;
DROP TABLE IF EXISTS caso_comunicaciones;
