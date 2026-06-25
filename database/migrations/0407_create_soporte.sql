-- @up
CREATE TABLE tickets_soporte (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    creado_por_usuario_id BIGINT UNSIGNED NULL,
    asignado_a_usuario_id BIGINT UNSIGNED NULL,
    asunto VARCHAR(180) NOT NULL,
    categoria VARCHAR(80) NULL,
    prioridad VARCHAR(30) NOT NULL DEFAULT 'media',
    estado VARCHAR(30) NOT NULL DEFAULT 'abierto',
    contexto_json JSON NULL,
    closed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tickets_soporte_id_firma (id, firma_id),
    KEY idx_tickets_firma_estado (firma_id, estado, prioridad, deleted_at),
    KEY idx_tickets_creador (firma_id, creado_por_usuario_id, estado, deleted_at),
    CONSTRAINT fk_tickets_soporte_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_tickets_soporte_creador FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_tickets_soporte_asignado FOREIGN KEY (asignado_a_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_mensajes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NOT NULL,
    autor_usuario_id BIGINT UNSIGNED NULL,
    visibilidad VARCHAR(20) NOT NULL DEFAULT 'firma',
    mensaje TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_ticket_mensajes_ticket (firma_id, ticket_id, created_at),
    KEY idx_ticket_mensajes_ticket_fk (ticket_id, firma_id),
    CONSTRAINT fk_ticket_mensajes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_ticket_mensajes_ticket FOREIGN KEY (ticket_id, firma_id) REFERENCES tickets_soporte (id, firma_id),
    CONSTRAINT fk_ticket_mensajes_autor FOREIGN KEY (autor_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('soporte.ver_propio', 'soporte', 'ver_propio', 'Consultar tickets propios'),
('soporte.ver_firma', 'soporte', 'ver_firma', 'Consultar tickets de la firma'),
('soporte.crear', 'soporte', 'crear', 'Crear tickets de soporte'),
('soporte.responder', 'soporte', 'responder', 'Responder tickets de soporte'),
('soporte.cambiar_estado', 'soporte', 'cambiar_estado', 'Cambiar estado de tickets'),
('soporte.ver_global', 'soporte', 'ver_global', 'Consultar cola global de soporte')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('soporte.ver_propio','soporte.ver_firma','soporte.crear','soporte.responder','soporte.cambiar_estado')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('soporte.ver_propio','soporte.ver_firma','soporte.crear','soporte.responder','soporte.cambiar_estado','soporte.ver_global');

DELETE FROM permisos WHERE codigo IN ('soporte.ver_propio','soporte.ver_firma','soporte.crear','soporte.responder','soporte.cambiar_estado','soporte.ver_global');
DROP TABLE IF EXISTS ticket_mensajes;
DROP TABLE IF EXISTS tickets_soporte;
