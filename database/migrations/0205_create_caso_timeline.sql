-- @up
CREATE TABLE caso_timeline (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NOT NULL,
    autor_usuario_id BIGINT UNSIGNED NULL,
    fecha_evento DATE NOT NULL,
    tipo_evento VARCHAR(80) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    contenido_publico TEXT NULL,
    contenido_interno TEXT NULL,
    visibilidad VARCHAR(20) NOT NULL DEFAULT 'interna',
    critico TINYINT(1) NOT NULL DEFAULT 0,
    documento_id BIGINT UNSIGNED NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_caso_timeline_id_firma (id, firma_id),
    KEY idx_caso_timeline_caso_fecha (firma_id, caso_id, fecha_evento, deleted_at),
    KEY idx_caso_timeline_visibilidad (firma_id, visibilidad, deleted_at),
    KEY idx_caso_timeline_autor (autor_usuario_id, created_at),
    CONSTRAINT fk_caso_timeline_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_caso_timeline_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_caso_timeline_autor FOREIGN KEY (autor_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('timeline.ver', 'timeline', 'ver', 'Consultar linea de tiempo'),
('timeline.crear', 'timeline', 'crear', 'Crear eventos de linea de tiempo'),
('timeline.editar', 'timeline', 'editar', 'Editar eventos de linea de tiempo'),
('timeline.eliminar', 'timeline', 'eliminar', 'Eliminar eventos de linea de tiempo'),
('timeline.publicar', 'timeline', 'publicar', 'Cambiar visibilidad publica de eventos')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('timeline.ver','timeline.crear','timeline.editar','timeline.eliminar','timeline.publicar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('timeline.ver','timeline.crear','timeline.editar','timeline.eliminar','timeline.publicar');

DELETE FROM permisos WHERE codigo IN ('timeline.ver','timeline.crear','timeline.editar','timeline.eliminar','timeline.publicar');
DROP TABLE IF EXISTS caso_timeline;
