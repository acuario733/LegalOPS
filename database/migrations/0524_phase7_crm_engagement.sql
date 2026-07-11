-- @up
ALTER TABLE booking_appointments
    ADD COLUMN recordatorio_24h_at DATETIME(6) NULL AFTER token_cancelacion,
    ADD COLUMN recordatorio_1h_at DATETIME(6) NULL AFTER recordatorio_24h_at,
    ADD KEY idx_booking_recordatorios (estado, fecha, hora_inicio, recordatorio_24h_at, recordatorio_1h_at);

ALTER TABLE prospectos
    ADD COLUMN estado_updated_at DATETIME(6) NULL AFTER estado;
UPDATE prospectos SET estado_updated_at=created_at WHERE estado_updated_at IS NULL;

CREATE TABLE prospecto_historial_estados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    prospecto_id BIGINT UNSIGNED NOT NULL,
    estado_anterior VARCHAR(40) NULL,
    estado_nuevo VARCHAR(40) NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_prospecto_historial (firma_id, prospecto_id, created_at),
    CONSTRAINT fk_prospecto_historial_prospecto FOREIGN KEY (prospecto_id, firma_id) REFERENCES prospectos (id, firma_id),
    CONSTRAINT fk_prospecto_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integraciones_oauth (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    proveedor ENUM('google_calendar','outlook','docusign') NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME(6) NULL,
    scope TEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_integracion_oauth (firma_id, usuario_id, proveedor),
    CONSTRAINT fk_integracion_oauth_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_integracion_oauth_usuario FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_credenciales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(254) NOT NULL,
    portal_password_hash VARCHAR(255) NULL,
    portal_activado_at DATETIME(6) NULL,
    portal_activacion_token VARCHAR(64) NULL,
    portal_activacion_expires_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_credencial_cliente (firma_id, cliente_id),
    UNIQUE KEY uq_portal_credencial_token (portal_activacion_token),
    KEY idx_portal_credencial_login (email, portal_activado_at),
    CONSTRAINT fk_portal_credencial_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_portal_credencial_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE caso_comunicaciones
    ADD UNIQUE KEY uq_caso_comunicaciones_id_firma (id, firma_id);

CREATE TABLE comunicacion_lecturas (
    comunicacion_id BIGINT UNSIGNED NOT NULL,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    leido_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (comunicacion_id, usuario_id),
    KEY idx_comunicacion_lecturas_usuario (firma_id, usuario_id, leido_at),
    CONSTRAINT fk_comunicacion_lectura_comunicacion FOREIGN KEY (comunicacion_id, firma_id) REFERENCES caso_comunicaciones (id, firma_id),
    CONSTRAINT fk_comunicacion_lectura_usuario FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo,modulo,accion,descripcion) VALUES
('integraciones.google_calendar','integraciones','google_calendar','Conectar y sincronizar Google Calendar')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion),modulo=VALUES(modulo),accion=VALUES(accion);

INSERT INTO rol_permiso (firma_id,rol_id,permiso_id,created_at)
SELECT r.firma_id,r.id,p.id,CURRENT_TIMESTAMP(6)
FROM roles r INNER JOIN permisos p ON p.codigo='integraciones.google_calendar'
WHERE r.codigo IN ('administrador','abogado') AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at=rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp INNER JOIN permisos p ON p.id=rp.permiso_id WHERE p.codigo='integraciones.google_calendar';
DELETE FROM permisos WHERE codigo='integraciones.google_calendar';
DROP TABLE IF EXISTS comunicacion_lecturas;
ALTER TABLE caso_comunicaciones DROP INDEX uq_caso_comunicaciones_id_firma;
DROP TABLE IF EXISTS portal_credenciales;
DROP TABLE IF EXISTS integraciones_oauth;
DROP TABLE IF EXISTS prospecto_historial_estados;
ALTER TABLE prospectos DROP COLUMN estado_updated_at;
ALTER TABLE booking_appointments
    DROP INDEX idx_booking_recordatorios,
    DROP COLUMN recordatorio_24h_at,
    DROP COLUMN recordatorio_1h_at;
