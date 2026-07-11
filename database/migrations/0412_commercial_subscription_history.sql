-- @up
ALTER TABLE firma_planes
    ADD COLUMN effective_at DATETIME(6) NULL AFTER estado,
    ADD COLUMN renews_at DATETIME(6) NULL AFTER starts_at,
    ADD COLUMN assigned_by_usuario_id BIGINT UNSIGNED NULL AFTER renews_at,
    ADD COLUMN motivo VARCHAR(500) NULL AFTER assigned_by_usuario_id,
    ADD COLUMN replaced_by_firma_plan_id BIGINT UNSIGNED NULL AFTER motivo;

UPDATE firma_planes
SET effective_at = starts_at
WHERE effective_at IS NULL;

ALTER TABLE firma_planes
    MODIFY effective_at DATETIME(6) NOT NULL,
    ADD CONSTRAINT fk_firma_planes_assigned_by FOREIGN KEY (assigned_by_usuario_id) REFERENCES usuarios (id),
    ADD CONSTRAINT fk_firma_planes_replaced_by FOREIGN KEY (replaced_by_firma_plan_id) REFERENCES firma_planes (id),
    ADD KEY idx_firma_planes_effective (firma_id, estado, effective_at, ends_at),
    ADD KEY idx_firma_planes_assigned_by (assigned_by_usuario_id);

CREATE TABLE firma_comercial_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    evento VARCHAR(60) NOT NULL,
    estado_comercial VARCHAR(30) NULL,
    estado_comercial_anterior VARCHAR(30) NULL,
    plan_id BIGINT UNSIGNED NULL,
    plan_anterior_id BIGINT UNSIGNED NULL,
    firma_plan_id BIGINT UNSIGNED NULL,
    recurso VARCHAR(100) NULL,
    limite_anterior INT UNSIGNED NULL,
    limite_nuevo INT UNSIGNED NULL,
    politica_anterior VARCHAR(20) NULL,
    politica_nueva VARCHAR(20) NULL,
    effective_at DATETIME(6) NULL,
    starts_at DATETIME(6) NULL,
    ends_at DATETIME(6) NULL,
    renews_at DATETIME(6) NULL,
    motivo VARCHAR(500) NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT fk_firma_comercial_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_firma_comercial_plan FOREIGN KEY (plan_id) REFERENCES planes (id),
    CONSTRAINT fk_firma_comercial_plan_anterior FOREIGN KEY (plan_anterior_id) REFERENCES planes (id),
    CONSTRAINT fk_firma_comercial_firma_plan FOREIGN KEY (firma_plan_id) REFERENCES firma_planes (id),
    CONSTRAINT fk_firma_comercial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id),
    KEY idx_firma_comercial_firma_fecha (firma_id, created_at),
    KEY idx_firma_comercial_evento (evento, created_at),
    KEY idx_firma_comercial_plan (plan_id),
    KEY idx_firma_comercial_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS firma_comercial_historial;

ALTER TABLE firma_planes
    DROP FOREIGN KEY fk_firma_planes_assigned_by,
    DROP FOREIGN KEY fk_firma_planes_replaced_by,
    DROP INDEX idx_firma_planes_effective,
    DROP INDEX idx_firma_planes_assigned_by,
    DROP COLUMN replaced_by_firma_plan_id,
    DROP COLUMN motivo,
    DROP COLUMN assigned_by_usuario_id,
    DROP COLUMN renews_at,
    DROP COLUMN effective_at;
