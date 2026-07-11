-- @up
CREATE TABLE calendario_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL COMMENT 'Tenant propietario',
    caso_id BIGINT UNSIGNED NULL COMMENT 'Caso asociado',
    titulo VARCHAR(255) NOT NULL COMMENT 'Titulo del evento',
    descripcion TEXT NULL COMMENT 'Descripcion del evento',
    inicio_at DATETIME(6) NOT NULL COMMENT 'Inicio del evento',
    fin_at DATETIME(6) NOT NULL COMMENT 'Fin del evento',
    todo_el_dia TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Marca evento de dia completo',
    tipo ENUM('audiencia','termino','tarea','reunion','otro') NOT NULL DEFAULT 'otro' COMMENT 'Tipo de evento',
    lugar VARCHAR(255) NULL COMMENT 'Lugar del evento',
    asistentes JSON NULL COMMENT 'Asistentes invitados',
    external_id VARCHAR(255) NULL COMMENT 'ID en Google Calendar o Outlook',
    color VARCHAR(7) NULL COMMENT 'Color hexadecimal para calendario',
    created_by_usuario_id BIGINT UNSIGNED NULL COMMENT 'Usuario creador',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT 'Fecha de creacion',
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT 'Fecha de actualizacion',
    deleted_at DATETIME(6) NULL COMMENT 'Soft delete',
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendario_eventos_id_firma (id, firma_id),
    KEY idx_calendario_eventos_rango (firma_id, inicio_at, fin_at),
    KEY idx_calendario_eventos_caso (firma_id, caso_id, inicio_at),
    CONSTRAINT fk_calendario_eventos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_calendario_eventos_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_calendario_eventos_usuario FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS calendario_eventos;
