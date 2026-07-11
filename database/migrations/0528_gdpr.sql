-- @up

ALTER TABLE clientes
    ADD COLUMN olvidado_at DATETIME(6) NULL AFTER deleted_at;

CREATE TABLE exportaciones_datos (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id    BIGINT UNSIGNED NOT NULL,
    cliente_id  BIGINT UNSIGNED NOT NULL,
    s3_key      VARCHAR(500) NULL,
    expires_at  DATETIME(6) NULL,
    created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_exportaciones_datos_firma_cliente (firma_id, cliente_id, created_at),
    KEY idx_exportaciones_datos_expira (expires_at),
    CONSTRAINT fk_exportaciones_datos_firma   FOREIGN KEY (firma_id)   REFERENCES firmas   (id),
    CONSTRAINT fk_exportaciones_datos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down

DROP TABLE IF EXISTS exportaciones_datos;

ALTER TABLE clientes
    DROP COLUMN olvidado_at;
