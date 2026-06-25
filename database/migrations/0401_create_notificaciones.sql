-- @up
CREATE TABLE notificaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    severidad VARCHAR(20) NOT NULL DEFAULT 'info',
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    origen_tipo VARCHAR(40) NOT NULL,
    origen_id BIGINT UNSIGNED NOT NULL,
    origen_url VARCHAR(255) NOT NULL,
    dedupe_key CHAR(64) NOT NULL,
    generated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    read_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_notificaciones_dedupe (firma_id, usuario_id, dedupe_key),
    KEY idx_notificaciones_usuario_estado (firma_id, usuario_id, estado, created_at),
    KEY idx_notificaciones_usuario_fk (usuario_id, firma_id),
    KEY idx_notificaciones_origen (firma_id, origen_tipo, origen_id),
    CONSTRAINT fk_notificaciones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_notificaciones_usuario FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('notificaciones.ver', 'notificaciones', 'ver', 'Consultar notificaciones internas'),
('notificaciones.marcar_leida', 'notificaciones', 'marcar_leida', 'Marcar notificaciones como leidas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('notificaciones.ver','notificaciones.marcar_leida')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('notificaciones.ver','notificaciones.marcar_leida');

DELETE FROM permisos WHERE codigo IN ('notificaciones.ver','notificaciones.marcar_leida');
DROP TABLE IF EXISTS notificaciones;
