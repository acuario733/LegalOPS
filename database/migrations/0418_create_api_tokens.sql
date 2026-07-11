-- @up
-- ─────────────────────────────────────────────────────────────────────────────
-- Tabla: api_tokens
-- Propósito: almacenar tokens de acceso para la API REST pública /api/v1/
--
-- TENANT FILTER: todos los tokens pertenecen a una firma (firma_id)
-- y opcionalmente a un usuario específico (usuario_id).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS api_tokens (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    firma_id      BIGINT UNSIGNED NOT NULL,
    usuario_id    BIGINT UNSIGNED NULL,                      -- NULL = token de aplicación (no personal)
    nombre        VARCHAR(100)    NOT NULL,                  -- Nombre descriptivo del token
    token_hash    VARCHAR(64)     NOT NULL UNIQUE,           -- SHA-256 del token en texto plano
    scopes        JSON            NULL,                      -- ["clientes:read","casos:read"] — NULL = todos
    ultimo_uso_at DATETIME(6)     NULL,
    expira_at     DATETIME(6)     NULL,                      -- NULL = no expira
    revocado_at   DATETIME(6)     NULL,
    created_at    DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at    DATETIME(6)     NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
                                           ON UPDATE CURRENT_TIMESTAMP(6),

    CONSTRAINT fk_api_tokens_firma    FOREIGN KEY (firma_id)   REFERENCES firmas(id)    ON DELETE CASCADE,
    CONSTRAINT fk_api_tokens_usuario  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE SET NULL,

    INDEX idx_api_tokens_firma   (firma_id),
    INDEX idx_api_tokens_usuario (usuario_id),
    INDEX idx_api_tokens_expira  (expira_at),
    INDEX idx_api_tokens_hash    (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tokens de acceso para la API REST pública /api/v1/';

-- @down
DROP TABLE IF EXISTS api_tokens;
