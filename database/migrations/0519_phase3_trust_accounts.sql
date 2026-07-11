-- @up
CREATE TABLE trust_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    cliente_id BIGINT UNSIGNED NOT NULL COMMENT 'Cliente titular de los fondos',
    caso_id BIGINT UNSIGNED NULL COMMENT 'Caso asociado si aplica',
    moneda CHAR(3) NOT NULL DEFAULT 'COP' COMMENT 'Moneda del saldo',
    saldo DECIMAL(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo fiduciario disponible',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT 'Fecha de actualizacion',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete de la cuenta',
    PRIMARY KEY (id),
    UNIQUE KEY uq_trust_accounts_id_firma (id, firma_id),
    UNIQUE KEY uq_trust_accounts_scope (firma_id, cliente_id, caso_id, moneda),
    KEY idx_trust_accounts_cliente (firma_id, cliente_id, deleted_at),
    CONSTRAINT fk_trust_accounts_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_trust_accounts_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_trust_accounts_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT ck_trust_accounts_saldo_positivo CHECK (saldo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trust_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    trust_account_id BIGINT UNSIGNED NOT NULL COMMENT 'Cuenta fiduciaria afectada',
    tipo ENUM('deposito','retiro','transferencia') NOT NULL COMMENT 'Tipo de movimiento inmutable',
    monto DECIMAL(18,2) NOT NULL COMMENT 'Monto positivo del movimiento',
    descripcion TEXT NOT NULL COMMENT 'Descripcion obligatoria para compliance',
    referencia VARCHAR(100) NULL COMMENT 'Referencia externa o interna',
    usuario_id BIGINT UNSIGNED NULL COMMENT 'Usuario que registra',
    honorario_id BIGINT UNSIGNED NULL COMMENT 'Honorario relacionado',
    pago_id BIGINT UNSIGNED NULL COMMENT 'Pago relacionado',
    fecha DATE NOT NULL COMMENT 'Fecha contable del movimiento',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de registro',
    PRIMARY KEY (id),
    KEY idx_trust_tx_account (trust_account_id, fecha),
    KEY idx_trust_tx_firma_fecha (firma_id, fecha),
    KEY idx_trust_tx_honorario (honorario_id, firma_id),
    KEY idx_trust_tx_pago (pago_id, firma_id),
    CONSTRAINT fk_trust_tx_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_trust_tx_account FOREIGN KEY (trust_account_id, firma_id) REFERENCES trust_accounts (id, firma_id),
    CONSTRAINT fk_trust_tx_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_trust_tx_honorario FOREIGN KEY (honorario_id, firma_id) REFERENCES honorarios (id, firma_id),
    CONSTRAINT fk_trust_tx_pago FOREIGN KEY (pago_id, firma_id) REFERENCES pagos (id, firma_id),
    CONSTRAINT ck_trust_tx_monto_positivo CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS trust_transactions;
DROP TABLE IF EXISTS trust_accounts;
