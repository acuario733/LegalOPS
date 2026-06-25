-- @up
CREATE TABLE portal_usuario_clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activo',
    created_by_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_usuario_cliente (firma_id, usuario_id, cliente_id),
    KEY idx_portal_usuario_cliente_estado (firma_id, usuario_id, estado),
    KEY idx_portal_usuario_clientes_usuario_fk (usuario_id, firma_id),
    KEY idx_portal_usuario_clientes_cliente_fk (cliente_id, firma_id),
    CONSTRAINT fk_portal_usuario_clientes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_portal_usuario_clientes_usuario FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_portal_usuario_clientes_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_portal_usuario_clientes_creador FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_accesos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    accion VARCHAR(80) NOT NULL,
    entidad_tipo VARCHAR(40) NULL,
    entidad_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_portal_accesos_cliente_fecha (firma_id, cliente_id, created_at),
    KEY idx_portal_accesos_usuario_fecha (firma_id, usuario_id, created_at),
    KEY idx_portal_accesos_usuario_fk (usuario_id, firma_id),
    KEY idx_portal_accesos_cliente_fk (cliente_id, firma_id),
    CONSTRAINT fk_portal_accesos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_portal_accesos_usuario FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_portal_accesos_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS portal_accesos;
DROP TABLE IF EXISTS portal_usuario_clientes;
