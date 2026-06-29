-- @up
CREATE TABLE booking_configs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(100) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    duracion_minutos INT NOT NULL DEFAULT 30,
    dias_activos JSON NOT NULL,
    hora_inicio TIME NOT NULL DEFAULT '09:00:00',
    hora_fin TIME NOT NULL DEFAULT '18:00:00',
    buffer_entre_citas INT NOT NULL DEFAULT 0,
    dias_anticipacion_min INT NOT NULL DEFAULT 1,
    dias_anticipacion_max INT NOT NULL DEFAULT 30,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    notificar_email VARCHAR(200) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_configs_slug (slug),
    UNIQUE KEY uq_booking_configs_id_firma (id, firma_id),
    UNIQUE KEY uq_booking_configs_usuario (firma_id, usuario_id),
    KEY idx_booking_configs_usuario (firma_id, usuario_id),
    KEY idx_booking_configs_slug (slug),
    CONSTRAINT fk_booking_configs_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_booking_configs_usuario_firma
        FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_appointments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    booking_config_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    nombre_cliente VARCHAR(200) NOT NULL,
    email_cliente VARCHAR(200) NOT NULL,
    telefono_cliente VARCHAR(50) NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    notas TEXT NULL,
    estado ENUM('confirmada', 'cancelada', 'completada') NOT NULL DEFAULT 'confirmada',
    prospecto_id BIGINT UNSIGNED NULL,
    token_cancelacion VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_appointments_token (token_cancelacion),
    KEY idx_booking_appointments_fecha (firma_id, booking_config_id, fecha),
    KEY idx_booking_appointments_token (token_cancelacion),
    KEY idx_booking_appointments_estado (firma_id, estado),
    KEY idx_booking_appointments_usuario (usuario_id, firma_id),
    KEY idx_booking_appointments_prospecto (prospecto_id, firma_id),
    CONSTRAINT fk_booking_appointments_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_booking_appointments_config_firma
        FOREIGN KEY (booking_config_id, firma_id) REFERENCES booking_configs (id, firma_id),
    CONSTRAINT fk_booking_appointments_usuario_firma
        FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_booking_appointments_prospecto_firma
        FOREIGN KEY (prospecto_id, firma_id) REFERENCES prospectos (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('booking.ver', 'booking', 'ver', 'Consultar configuracion y citas'),
('booking.crear', 'booking', 'crear', 'Crear configuracion de reservas'),
('booking.editar', 'booking', 'editar', 'Editar configuracion y citas'),
('booking.eliminar', 'booking', 'eliminar', 'Eliminar configuracion de reservas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('booking.ver', 'booking.crear', 'booking.editar', 'booking.eliminar')
WHERE r.codigo = 'administrador' AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('booking.ver', 'booking.crear', 'booking.editar')
WHERE r.codigo = 'abogado' AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('booking.ver', 'booking.crear', 'booking.editar', 'booking.eliminar');

DELETE FROM permisos WHERE codigo IN ('booking.ver', 'booking.crear', 'booking.editar', 'booking.eliminar');
DROP TABLE IF EXISTS booking_appointments;
DROP TABLE IF EXISTS booking_configs;
