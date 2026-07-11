-- @up
CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue VARCHAR(50) NOT NULL DEFAULT 'default' COMMENT 'Nombre de cola logica',
    payload LONGTEXT NOT NULL COMMENT 'JSON con job_class y datos de ejecucion',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Intentos realizados',
    reserved_at INT UNSIGNED NULL COMMENT 'Unix timestamp de reserva por worker',
    available_at INT UNSIGNED NOT NULL COMMENT 'Unix timestamp a partir del cual se procesa',
    created_at INT UNSIGNED NOT NULL COMMENT 'Unix timestamp de creacion',
    PRIMARY KEY (id),
    KEY idx_jobs_queue_available (queue, available_at),
    KEY idx_jobs_reserved (reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL COMMENT 'Identificador unico del fallo',
    connection TEXT NOT NULL COMMENT 'Conexion usada por el worker',
    queue TEXT NOT NULL COMMENT 'Cola original',
    payload LONGTEXT NOT NULL COMMENT 'Payload JSON original',
    exception LONGTEXT NOT NULL COMMENT 'Excepcion serializada para diagnostico',
    failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha del fallo definitivo',
    PRIMARY KEY (id),
    UNIQUE KEY uq_failed_jobs_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS failed_jobs;
DROP TABLE IF EXISTS jobs;
