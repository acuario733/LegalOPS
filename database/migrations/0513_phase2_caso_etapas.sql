-- @up
CREATE TABLE caso_etapas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    caso_id BIGINT UNSIGNED NOT NULL COMMENT 'Caso al que pertenece la etapa',
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre visible de la etapa',
    orden TINYINT UNSIGNED NOT NULL COMMENT 'Orden progresivo dentro del caso',
    completada_at DATETIME(6) NULL COMMENT 'Fecha de completitud de la etapa',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_caso_etapas_id_firma (id, firma_id),
    UNIQUE KEY uq_caso_etapas_orden (firma_id, caso_id, orden),
    KEY idx_caso_etapas_caso (firma_id, caso_id, deleted_at),
    CONSTRAINT fk_caso_etapas_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_caso_etapas_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE casos
    ADD COLUMN etapa_actual_id BIGINT UNSIGNED NULL COMMENT 'Etapa actual del caso' AFTER prioridad,
    ADD KEY idx_casos_etapa_actual (etapa_actual_id, firma_id),
    ADD CONSTRAINT fk_casos_etapa_actual FOREIGN KEY (etapa_actual_id, firma_id) REFERENCES caso_etapas (id, firma_id);

-- @down
ALTER TABLE casos DROP FOREIGN KEY fk_casos_etapa_actual;
ALTER TABLE casos DROP INDEX idx_casos_etapa_actual;
ALTER TABLE casos DROP COLUMN etapa_actual_id;
DROP TABLE IF EXISTS caso_etapas;
