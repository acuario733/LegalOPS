-- Fase 6: MFA (Multi-Factor Authentication)
-- Tabla para configuracion y secretos MFA por usuario

CREATE TABLE IF NOT EXISTS user_mfa (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id      BIGINT UNSIGNED NOT NULL,
    usuario_id    BIGINT UNSIGNED NOT NULL,
    totp_secret_enc   TEXT        NOT NULL COMMENT 'Secreto TOTP cifrado con SensitiveDataService',
    recovery_codes_enc TEXT       NOT NULL COMMENT 'JSON array de codigos de recuperacion cifrado',
    habilitado    TINYINT(1)      NOT NULL DEFAULT 0,
    verified_at   DATETIME        NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_mfa_usuario (usuario_id),
    KEY idx_user_mfa_firma (firma_id),
    CONSTRAINT fk_user_mfa_firma    FOREIGN KEY (firma_id)   REFERENCES firmas(id)    ON DELETE CASCADE,
    CONSTRAINT fk_user_mfa_usuario  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columna para activacion de MFA en la tabla de usuarios
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS mfa_habilitado TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo;
