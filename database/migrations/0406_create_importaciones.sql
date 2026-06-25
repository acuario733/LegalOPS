-- @up
CREATE TABLE importaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(60) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'previsualizada',
    archivo_path VARCHAR(500) NULL,
    nombre_original VARCHAR(255) NULL,
    filas_total INT UNSIGNED NOT NULL DEFAULT 0,
    filas_validas INT UNSIGNED NOT NULL DEFAULT 0,
    filas_error INT UNSIGNED NOT NULL DEFAULT 0,
    errores_json JSON NULL,
    resultado_json JSON NULL,
    expires_at DATETIME(6) NULL,
    confirmed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_importaciones_firma_tipo (firma_id, tipo, estado, created_at),
    KEY idx_importaciones_expira (expires_at),
    CONSTRAINT fk_importaciones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_importaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('importaciones.ver', 'importaciones', 'ver', 'Consultar importaciones'),
('importaciones.crear', 'importaciones', 'crear', 'Previsualizar importaciones CSV'),
('importaciones.ejecutar', 'importaciones', 'ejecutar', 'Confirmar importaciones CSV')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('importaciones.ver','importaciones.crear','importaciones.ejecutar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('importaciones.ver','importaciones.crear','importaciones.ejecutar');

DELETE FROM permisos WHERE codigo IN ('importaciones.ver','importaciones.crear','importaciones.ejecutar');
DROP TABLE IF EXISTS importaciones;
