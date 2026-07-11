-- @up
ALTER TABLE firmas
    ADD COLUMN logo_s3_key VARCHAR(500) NULL AFTER timezone;

ALTER TABLE honorarios
    ADD COLUMN numero VARCHAR(40) NULL AFTER caso_id,
    ADD COLUMN fecha_vencimiento DATE NULL AFTER fecha_acuerdo,
    ADD COLUMN enviado_at DATETIME(6) NULL AFTER pdf_s3_key,
    ADD COLUMN enviado_a_email VARCHAR(254) NULL AFTER enviado_at,
    ADD COLUMN ultimo_recordatorio_at DATETIME(6) NULL AFTER enviado_a_email,
    ADD COLUMN retainer_id BIGINT UNSIGNED NULL AFTER ultimo_recordatorio_at,
    ADD COLUMN origen ENUM('manual','retainer') NOT NULL DEFAULT 'manual' AFTER retainer_id,
    ADD COLUMN anulado_motivo TEXT NULL AFTER origen,
    ADD COLUMN anulado_at DATETIME(6) NULL AFTER anulado_motivo,
    ADD COLUMN anulado_por_usuario_id BIGINT UNSIGNED NULL AFTER anulado_at,
    ADD UNIQUE KEY uq_honorarios_numero_firma (firma_id, numero),
    ADD KEY idx_honorarios_vencimiento (firma_id, estado, fecha_vencimiento, deleted_at),
    ADD CONSTRAINT fk_honorarios_anulado_usuario FOREIGN KEY (anulado_por_usuario_id) REFERENCES usuarios (id);

UPDATE honorarios
SET numero = CONCAT('FAC-', LPAD(id, 8, '0')),
    fecha_vencimiento = DATE_ADD(fecha_acuerdo, INTERVAL 30 DAY)
WHERE numero IS NULL OR fecha_vencimiento IS NULL;

CREATE TABLE honorario_retainers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    monto DECIMAL(15,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    dia_cobro TINYINT UNSIGNED NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    proximo_cobro_at DATE NOT NULL,
    ultimo_cobro_at DATE NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_honorario_retainers_id_firma (id, firma_id),
    KEY idx_retainers_cobro (firma_id, activo, proximo_cobro_at, deleted_at),
    CONSTRAINT fk_retainers_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_retainers_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_retainers_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT chk_retainers_monto CHECK (monto > 0),
    CONSTRAINT chk_retainers_dia CHECK (dia_cobro BETWEEN 1 AND 28)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE honorarios
    ADD CONSTRAINT fk_honorarios_retainer FOREIGN KEY (retainer_id, firma_id)
        REFERENCES honorario_retainers (id, firma_id);

CREATE TABLE job_schedules (
    schedule_key VARCHAR(100) NOT NULL,
    last_run_at DATETIME(6) NOT NULL,
    PRIMARY KEY (schedule_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS job_schedules;
ALTER TABLE honorarios DROP FOREIGN KEY fk_honorarios_retainer;
DROP TABLE IF EXISTS honorario_retainers;
ALTER TABLE honorarios
    DROP FOREIGN KEY fk_honorarios_anulado_usuario,
    DROP INDEX uq_honorarios_numero_firma,
    DROP INDEX idx_honorarios_vencimiento,
    DROP COLUMN numero,
    DROP COLUMN fecha_vencimiento,
    DROP COLUMN enviado_at,
    DROP COLUMN enviado_a_email,
    DROP COLUMN ultimo_recordatorio_at,
    DROP COLUMN retainer_id,
    DROP COLUMN origen,
    DROP COLUMN anulado_motivo,
    DROP COLUMN anulado_at,
    DROP COLUMN anulado_por_usuario_id;
ALTER TABLE firmas DROP COLUMN logo_s3_key;
