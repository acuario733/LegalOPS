-- @up
CREATE TABLE plan_limites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id BIGINT UNSIGNED NOT NULL,
    recurso VARCHAR(100) NOT NULL,
    limite INT UNSIGNED NULL,
    politica VARCHAR(20) NOT NULL DEFAULT 'block',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_plan_limites_recurso (plan_id, recurso),
    CONSTRAINT fk_plan_limites_plan FOREIGN KEY (plan_id) REFERENCES planes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_firma_planes_estado ON firma_planes (estado, starts_at, ends_at);

-- @down
DROP INDEX idx_firma_planes_estado ON firma_planes;
DROP TABLE IF EXISTS plan_limites;

