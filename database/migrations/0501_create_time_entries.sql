-- @up
CREATE TABLE time_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NOT NULL,
    tarea_id BIGINT UNSIGNED NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATE NOT NULL,
    duracion_minutos INT UNSIGNED NOT NULL,
    tarifa_hora DECIMAL(10,2) NULL,
    es_facturable TINYINT(1) NOT NULL DEFAULT 1,
    facturado TINYINT(1) NOT NULL DEFAULT 0,
    honorario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_time_entries_id_firma (id, firma_id),
    KEY idx_time_entries_caso (firma_id, caso_id),
    KEY idx_time_entries_usuario_fecha (firma_id, usuario_id, fecha),
    KEY idx_time_entries_facturado (firma_id, facturado),
    KEY idx_time_entries_tarea (tarea_id, firma_id),
    KEY idx_time_entries_honorario (honorario_id, firma_id),
    CONSTRAINT fk_time_entries_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_time_entries_caso_firma
        FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_time_entries_tarea_firma
        FOREIGN KEY (tarea_id, firma_id) REFERENCES tareas (id, firma_id),
    CONSTRAINT fk_time_entries_usuario_firma
        FOREIGN KEY (usuario_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_time_entries_honorario_firma
        FOREIGN KEY (honorario_id, firma_id) REFERENCES honorarios (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS time_entries;
