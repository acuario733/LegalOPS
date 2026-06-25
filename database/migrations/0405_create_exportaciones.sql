-- @up
CREATE TABLE exportaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    tipo VARCHAR(60) NOT NULL,
    filtros_json JSON NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'generada',
    archivo_path VARCHAR(500) NULL,
    filas INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_exportaciones_firma_tipo (firma_id, tipo, created_at),
    KEY idx_exportaciones_expira (expires_at),
    CONSTRAINT fk_exportaciones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_exportaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('reportes.ver', 'reportes', 'ver', 'Consultar reportes'),
('reportes.exportar', 'reportes', 'exportar', 'Exportar reportes CSV')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('reportes.ver','reportes.exportar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('reportes.ver','reportes.exportar');

DELETE FROM permisos WHERE codigo IN ('reportes.ver','reportes.exportar');
DROP TABLE IF EXISTS exportaciones;
