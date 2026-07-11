-- @up
CREATE TABLE trust_reportes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    periodo CHAR(7) NOT NULL COMMENT 'Periodo YYYY-MM conciliado',
    s3_key VARCHAR(500) NOT NULL COMMENT 'Ruta S3 del reporte PDF',
    generado_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de generacion',
    generado_por_usuario_id BIGINT UNSIGNED NULL COMMENT 'Usuario que genero el reporte',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_trust_reportes_periodo (firma_id, periodo),
    KEY idx_trust_reportes_firma_fecha (firma_id, generado_at),
    CONSTRAINT fk_trust_reportes_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_trust_reportes_usuario FOREIGN KEY (generado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS trust_reportes;
