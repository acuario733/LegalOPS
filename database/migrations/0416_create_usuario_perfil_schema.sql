-- @up
ALTER TABLE usuarios
    ADD COLUMN nombres VARCHAR(160) NULL AFTER nombre,
    ADD COLUMN apellidos VARCHAR(160) NULL AFTER nombres,
    ADD COLUMN tipo_documento_id BIGINT UNSIGNED NULL AFTER apellidos,
    ADD COLUMN numero_documento VARCHAR(80) NULL AFTER tipo_documento_id,
    ADD COLUMN numero_documento_normalizado VARCHAR(80) NULL AFTER numero_documento,
    ADD COLUMN telefono VARCHAR(40) NULL AFTER numero_documento_normalizado,
    ADD COLUMN cargo VARCHAR(160) NULL AFTER telefono,
    ADD COLUMN foto_perfil_path VARCHAR(500) NULL AFTER cargo,
    ADD COLUMN es_abogado TINYINT(1) NOT NULL DEFAULT 0 AFTER foto_perfil_path,
    ADD COLUMN tiene_tarjeta_profesional TINYINT(1) NOT NULL DEFAULT 0 AFTER es_abogado,
    ADD COLUMN numero_tarjeta_profesional VARCHAR(80) NULL AFTER tiene_tarjeta_profesional,
    ADD COLUMN numero_tarjeta_profesional_normalizado VARCHAR(80) NULL AFTER numero_tarjeta_profesional,
    ADD COLUMN tarjeta_profesional_verificacion_estado VARCHAR(20) NULL AFTER numero_tarjeta_profesional_normalizado,
    ADD COLUMN fecha_verificacion_tarjeta DATETIME(6) NULL AFTER tarjeta_profesional_verificacion_estado,
    ADD COLUMN usuario_verificador_tarjeta_id BIGINT UNSIGNED NULL AFTER fecha_verificacion_tarjeta,
    ADD COLUMN observacion_verificacion_tarjeta VARCHAR(1000) NULL AFTER usuario_verificador_tarjeta_id;

UPDATE usuarios
SET nombres = nombre
WHERE nombres IS NULL;

ALTER TABLE usuarios
    ADD CONSTRAINT fk_usuarios_tipo_documento
        FOREIGN KEY (tipo_documento_id) REFERENCES catalogo_items (id),
    ADD CONSTRAINT fk_usuarios_verificador_firma
        FOREIGN KEY (usuario_verificador_tarjeta_id, firma_id) REFERENCES usuarios (id, firma_id),
    ADD CONSTRAINT chk_usuarios_es_abogado
        CHECK (es_abogado IN (0, 1)),
    ADD CONSTRAINT chk_usuarios_tiene_tarjeta
        CHECK (tiene_tarjeta_profesional IN (0, 1)),
    ADD CONSTRAINT chk_usuarios_tarjeta_consistencia
        CHECK (
            (
                tiene_tarjeta_profesional = 0
                AND numero_tarjeta_profesional IS NULL
                AND numero_tarjeta_profesional_normalizado IS NULL
                AND tarjeta_profesional_verificacion_estado IS NULL
                AND fecha_verificacion_tarjeta IS NULL
                AND usuario_verificador_tarjeta_id IS NULL
                AND observacion_verificacion_tarjeta IS NULL
            )
            OR
            (
                tiene_tarjeta_profesional = 1
                AND NULLIF(TRIM(numero_tarjeta_profesional), '') IS NOT NULL
                AND NULLIF(TRIM(numero_tarjeta_profesional_normalizado), '') IS NOT NULL
                AND tarjeta_profesional_verificacion_estado IN ('pendiente', 'verificada', 'rechazada')
                AND (
                    (
                        tarjeta_profesional_verificacion_estado = 'pendiente'
                        AND fecha_verificacion_tarjeta IS NULL
                        AND usuario_verificador_tarjeta_id IS NULL
                        AND observacion_verificacion_tarjeta IS NULL
                    )
                    OR
                    (
                        tarjeta_profesional_verificacion_estado IN ('verificada', 'rechazada')
                        AND fecha_verificacion_tarjeta IS NOT NULL
                        AND usuario_verificador_tarjeta_id IS NOT NULL
                    )
                )
            )
        ),
    ADD UNIQUE KEY uq_usuarios_documento_firma
        (firma_id, tipo_documento_id, numero_documento_normalizado),
    ADD KEY idx_usuarios_tarjeta_pendiente
        (firma_id, tarjeta_profesional_verificacion_estado, deleted_at),
    ADD KEY idx_usuarios_tipo_documento
        (tipo_documento_id),
    ADD KEY idx_usuarios_verificador_firma
        (usuario_verificador_tarjeta_id, firma_id);

CREATE TABLE usuario_cambios_sensibles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    usuario_afectado_id BIGINT UNSIGNED NOT NULL,
    usuario_actor_id BIGINT UNSIGNED NULL,
    campo VARCHAR(80) NOT NULL,
    valor_anterior_enmascarado VARCHAR(255) NULL,
    valor_nuevo_enmascarado VARCHAR(255) NULL,
    valor_anterior_hash CHAR(64) NULL,
    valor_nuevo_hash CHAR(64) NULL,
    origen VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT fk_usuario_cambios_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_usuario_cambios_afectado_firma
        FOREIGN KEY (usuario_afectado_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT fk_usuario_cambios_actor_firma
        FOREIGN KEY (usuario_actor_id, firma_id) REFERENCES usuarios (id, firma_id),
    CONSTRAINT chk_usuario_cambios_origen
        CHECK (origen IN ('mi_perfil', 'usuarios')),
    CONSTRAINT chk_usuario_cambios_hash_anterior
        CHECK (valor_anterior_hash IS NULL OR valor_anterior_hash REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT chk_usuario_cambios_hash_nuevo
        CHECK (valor_nuevo_hash IS NULL OR valor_nuevo_hash REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT chk_usuario_cambios_dato_sensible
        CHECK (
            campo NOT IN ('numero_documento', 'numero_tarjeta_profesional')
            OR
            (
                (
                    valor_anterior_enmascarado IS NULL
                    OR (
                        INSTR(valor_anterior_enmascarado, '*') > 0
                        AND valor_anterior_hash IS NOT NULL
                    )
                )
                AND
                (
                    valor_nuevo_enmascarado IS NULL
                    OR (
                        INSTR(valor_nuevo_enmascarado, '*') > 0
                        AND valor_nuevo_hash IS NOT NULL
                    )
                )
            )
        ),
    KEY idx_usuario_cambios_afectado_fecha
        (firma_id, usuario_afectado_id, created_at),
    KEY idx_usuario_cambios_actor_fecha
        (firma_id, usuario_actor_id, created_at),
    KEY idx_usuario_cambios_campo_fecha
        (firma_id, campo, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- @down
DROP TABLE IF EXISTS usuario_cambios_sensibles;

ALTER TABLE usuarios
    DROP FOREIGN KEY fk_usuarios_tipo_documento,
    DROP FOREIGN KEY fk_usuarios_verificador_firma,
    DROP CONSTRAINT chk_usuarios_es_abogado,
    DROP CONSTRAINT chk_usuarios_tiene_tarjeta,
    DROP CONSTRAINT chk_usuarios_tarjeta_consistencia,
    DROP INDEX uq_usuarios_documento_firma,
    DROP INDEX idx_usuarios_tarjeta_pendiente,
    DROP INDEX idx_usuarios_tipo_documento,
    DROP INDEX idx_usuarios_verificador_firma,
    DROP COLUMN observacion_verificacion_tarjeta,
    DROP COLUMN usuario_verificador_tarjeta_id,
    DROP COLUMN fecha_verificacion_tarjeta,
    DROP COLUMN tarjeta_profesional_verificacion_estado,
    DROP COLUMN numero_tarjeta_profesional_normalizado,
    DROP COLUMN numero_tarjeta_profesional,
    DROP COLUMN tiene_tarjeta_profesional,
    DROP COLUMN es_abogado,
    DROP COLUMN foto_perfil_path,
    DROP COLUMN cargo,
    DROP COLUMN telefono,
    DROP COLUMN numero_documento_normalizado,
    DROP COLUMN numero_documento,
    DROP COLUMN tipo_documento_id,
    DROP COLUMN apellidos,
    DROP COLUMN nombres;
