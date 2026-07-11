-- @up
CREATE TABLE webhooks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    url VARCHAR(500) NOT NULL COMMENT 'URL destino',
    eventos JSON NOT NULL COMMENT 'Eventos suscritos',
    secret_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 del secreto del webhook',
    activo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Indica si el webhook esta activo',
    created_by_usuario_id BIGINT UNSIGNED NULL COMMENT 'Usuario creador',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT 'Fecha de actualizacion',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhooks_id_firma (id, firma_id),
    KEY idx_webhooks_firma_activo (firma_id, activo, deleted_at),
    CONSTRAINT fk_webhooks_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_webhooks_usuario FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_entregas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    webhook_id BIGINT UNSIGNED NOT NULL COMMENT 'Webhook destino',
    evento VARCHAR(100) NOT NULL COMMENT 'Evento enviado',
    payload LONGTEXT NOT NULL COMMENT 'JSON enviado',
    status_http SMALLINT UNSIGNED NULL COMMENT 'Codigo HTTP recibido',
    respuesta TEXT NULL COMMENT 'Cuerpo de respuesta truncado',
    intento TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Intento de entrega',
    entregado_at DATETIME(6) NULL COMMENT 'Fecha de entrega exitosa',
    error TEXT NULL COMMENT 'Error de entrega truncado',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    PRIMARY KEY (id),
    KEY idx_webhook_entregas_webhook (firma_id, webhook_id, created_at),
    KEY idx_webhook_entregas_evento (firma_id, evento, created_at),
    CONSTRAINT fk_webhook_entregas_webhook FOREIGN KEY (webhook_id, firma_id) REFERENCES webhooks (id, firma_id),
    CONSTRAINT fk_webhook_entregas_firma FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS webhook_entregas;
DROP TABLE IF EXISTS webhooks;
