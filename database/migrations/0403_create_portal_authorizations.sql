-- @up
CREATE TABLE caso_permisos_portal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'autorizado',
    observacion_publica TEXT NULL,
    autorizado_por_usuario_id BIGINT UNSIGNED NULL,
    autorizado_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    revocado_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_caso_portal (firma_id, cliente_id, caso_id),
    KEY idx_caso_portal_estado (firma_id, cliente_id, estado),
    KEY idx_caso_portal_cliente_fk (cliente_id, firma_id),
    KEY idx_caso_portal_caso_fk (caso_id, firma_id),
    CONSTRAINT fk_caso_portal_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_caso_portal_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_caso_portal_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_caso_portal_usuario FOREIGN KEY (autorizado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documento_permisos_portal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    documento_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'autorizado',
    observacion_publica TEXT NULL,
    autorizado_por_usuario_id BIGINT UNSIGNED NULL,
    autorizado_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    revocado_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_documento_portal (firma_id, cliente_id, documento_id),
    KEY idx_documento_portal_estado (firma_id, cliente_id, estado),
    KEY idx_documento_portal_cliente_fk (cliente_id, firma_id),
    KEY idx_documento_portal_documento_fk (documento_id, firma_id),
    CONSTRAINT fk_documento_portal_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_documento_portal_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_documento_portal_documento FOREIGN KEY (documento_id, firma_id) REFERENCES documentos (id, firma_id),
    CONSTRAINT fk_documento_portal_usuario FOREIGN KEY (autorizado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finanza_permisos_portal (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    tipo_finanza VARCHAR(20) NOT NULL,
    honorario_id BIGINT UNSIGNED NULL,
    pago_id BIGINT UNSIGNED NULL,
    gasto_id BIGINT UNSIGNED NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'autorizado',
    observacion_publica TEXT NULL,
    autorizado_por_usuario_id BIGINT UNSIGNED NULL,
    autorizado_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    revocado_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_finanza_portal_honorario (firma_id, cliente_id, tipo_finanza, honorario_id),
    UNIQUE KEY uq_finanza_portal_pago (firma_id, cliente_id, tipo_finanza, pago_id),
    UNIQUE KEY uq_finanza_portal_gasto (firma_id, cliente_id, tipo_finanza, gasto_id),
    KEY idx_finanza_portal_estado (firma_id, cliente_id, estado),
    KEY idx_finanza_portal_cliente_fk (cliente_id, firma_id),
    KEY idx_finanza_portal_honorario_fk (honorario_id, firma_id),
    KEY idx_finanza_portal_pago_fk (pago_id, firma_id),
    KEY idx_finanza_portal_gasto_fk (gasto_id, firma_id),
    CONSTRAINT fk_finanza_portal_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_finanza_portal_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_finanza_portal_honorario FOREIGN KEY (honorario_id, firma_id) REFERENCES honorarios (id, firma_id),
    CONSTRAINT fk_finanza_portal_pago FOREIGN KEY (pago_id, firma_id) REFERENCES pagos (id, firma_id),
    CONSTRAINT fk_finanza_portal_gasto FOREIGN KEY (gasto_id, firma_id) REFERENCES gastos (id, firma_id),
    CONSTRAINT fk_finanza_portal_usuario FOREIGN KEY (autorizado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_observaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    documento_id BIGINT UNSIGNED NULL,
    honorario_id BIGINT UNSIGNED NULL,
    pago_id BIGINT UNSIGNED NULL,
    gasto_id BIGINT UNSIGNED NULL,
    observacion_publica TEXT NULL,
    observacion_interna TEXT NULL,
    created_by_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_portal_observaciones_cliente (firma_id, cliente_id, created_at),
    KEY idx_portal_observaciones_cliente_fk (cliente_id, firma_id),
    KEY idx_portal_observaciones_caso_fk (caso_id, firma_id),
    KEY idx_portal_observaciones_documento_fk (documento_id, firma_id),
    KEY idx_portal_observaciones_honorario_fk (honorario_id, firma_id),
    KEY idx_portal_observaciones_pago_fk (pago_id, firma_id),
    KEY idx_portal_observaciones_gasto_fk (gasto_id, firma_id),
    CONSTRAINT fk_portal_observaciones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_portal_observaciones_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_portal_observaciones_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_portal_observaciones_documento FOREIGN KEY (documento_id, firma_id) REFERENCES documentos (id, firma_id),
    CONSTRAINT fk_portal_observaciones_honorario FOREIGN KEY (honorario_id, firma_id) REFERENCES honorarios (id, firma_id),
    CONSTRAINT fk_portal_observaciones_pago FOREIGN KEY (pago_id, firma_id) REFERENCES pagos (id, firma_id),
    CONSTRAINT fk_portal_observaciones_gasto FOREIGN KEY (gasto_id, firma_id) REFERENCES gastos (id, firma_id),
    CONSTRAINT fk_portal_observaciones_usuario FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('portal.autorizar', 'portal', 'autorizar', 'Autorizar informacion visible en el portal cliente')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('portal.autorizar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('portal.autorizar');

DELETE FROM permisos WHERE codigo IN ('portal.autorizar');
DROP TABLE IF EXISTS portal_observaciones;
DROP TABLE IF EXISTS finanza_permisos_portal;
DROP TABLE IF EXISTS documento_permisos_portal;
DROP TABLE IF EXISTS caso_permisos_portal;
