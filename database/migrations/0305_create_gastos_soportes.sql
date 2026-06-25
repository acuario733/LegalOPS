-- @up
CREATE TABLE gastos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    concepto VARCHAR(180) NOT NULL,
    concepto_normalizado VARCHAR(180) NOT NULL,
    categoria VARCHAR(100) NULL,
    monto DECIMAL(14,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    fecha_gasto DATE NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'registrado',
    observaciones TEXT NULL,
    registrado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gastos_id_firma (id, firma_id),
    KEY idx_gastos_cliente_fecha (firma_id, cliente_id, fecha_gasto, deleted_at),
    KEY idx_gastos_caso_fecha (firma_id, caso_id, fecha_gasto, deleted_at),
    KEY idx_gastos_categoria (firma_id, categoria, deleted_at),
    KEY idx_gastos_usuario (registrado_por_usuario_id),
    CONSTRAINT fk_gastos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_gastos_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_gastos_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_gastos_usuario FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gasto_soportes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    gasto_id BIGINT UNSIGNED NOT NULL,
    documento_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_gasto_soporte (firma_id, gasto_id, documento_id),
    KEY idx_gasto_soportes_documento (firma_id, documento_id),
    CONSTRAINT fk_gasto_soportes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_gasto_soportes_gasto FOREIGN KEY (gasto_id, firma_id) REFERENCES gastos (id, firma_id),
    CONSTRAINT fk_gasto_soportes_documento FOREIGN KEY (documento_id, firma_id) REFERENCES documentos (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE documentos
    ADD CONSTRAINT fk_documentos_gasto FOREIGN KEY (gasto_id, firma_id) REFERENCES gastos (id, firma_id);

-- @down
ALTER TABLE documentos DROP FOREIGN KEY fk_documentos_gasto;
DROP TABLE IF EXISTS gasto_soportes;
DROP TABLE IF EXISTS gastos;
