-- @up
CREATE TABLE honorario_lineas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    honorario_id BIGINT UNSIGNED NOT NULL COMMENT 'Factura u honorario padre',
    descripcion TEXT NOT NULL COMMENT 'Descripcion de la linea',
    cantidad DECIMAL(10,2) NOT NULL DEFAULT 1.00 COMMENT 'Cantidad facturada',
    tarifa DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tarifa unitaria',
    monto DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto total de la linea',
    tipo ENUM('time_entry','gasto','honorario_manual') NOT NULL DEFAULT 'honorario_manual' COMMENT 'Origen de la linea',
    referencia_id BIGINT UNSIGNED NULL COMMENT 'Id del registro origen',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_honorario_lineas_id_firma (id, firma_id),
    KEY idx_honorario_lineas_honorario (firma_id, honorario_id, deleted_at),
    KEY idx_honorario_lineas_referencia (firma_id, tipo, referencia_id),
    CONSTRAINT fk_honorario_lineas_honorario FOREIGN KEY (honorario_id, firma_id) REFERENCES honorarios (id, firma_id),
    CONSTRAINT fk_honorario_lineas_firma FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS honorario_lineas;
