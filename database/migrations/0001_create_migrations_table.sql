-- @up
CREATE TABLE IF NOT EXISTS migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(191) NOT NULL,
    batch INT UNSIGNED NOT NULL,
    duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_migrations_migration (migration),
    KEY idx_migrations_batch (batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Esta migración no se revierte automáticamente porque contiene el historial
-- usado por el propio runner. En un entorno descartable, la estrategia manual
-- consiste en respaldar el historial y ejecutar: DROP TABLE migrations.
-- @down
