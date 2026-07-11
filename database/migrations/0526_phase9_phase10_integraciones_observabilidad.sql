-- @up
ALTER TABLE webhooks
    ADD COLUMN secret_encrypted TEXT NULL AFTER secret_hash;

CREATE TABLE job_stats (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    fecha DATE NOT NULL,
    queue VARCHAR(50) NOT NULL,
    procesados INT UNSIGNED NOT NULL DEFAULT 0,
    fallidos INT UNSIGNED NOT NULL DEFAULT 0,
    tiempo_total_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
    tiempo_promedio_ms INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_stats_fecha_queue (fecha,queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS job_stats;
ALTER TABLE webhooks DROP COLUMN secret_encrypted;
