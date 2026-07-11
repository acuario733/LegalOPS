-- @up
ALTER TABLE casos
    ADD COLUMN numero VARCHAR(20) NULL COMMENT 'Numero secuencial por firma y anio' AFTER firma_id,
    ADD UNIQUE KEY ux_casos_firma_numero (firma_id, numero);

CREATE TABLE caso_secuencias (
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    anio YEAR NOT NULL COMMENT 'Anio del contador',
    ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ultimo consecutivo emitido',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT 'Ultima actualizacion',
    PRIMARY KEY (firma_id, anio),
    CONSTRAINT fk_caso_secuencias_firma FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS caso_secuencias;
ALTER TABLE casos DROP INDEX ux_casos_firma_numero;
ALTER TABLE casos DROP COLUMN numero;
