-- @up
CREATE TABLE pagos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    honorario_id BIGINT UNSIGNED NULL,
    fecha_pago DATE NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    metodo_pago VARCHAR(80) NOT NULL,
    referencia VARCHAR(180) NULL,
    referencia_hash CHAR(64) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'registrado',
    observaciones TEXT NULL,
    registrado_por_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pagos_id_firma (id, firma_id),
    KEY idx_pagos_cliente_fecha (firma_id, cliente_id, fecha_pago, deleted_at),
    KEY idx_pagos_caso_fecha (firma_id, caso_id, fecha_pago, deleted_at),
    KEY idx_pagos_honorario (firma_id, honorario_id, deleted_at),
    KEY idx_pagos_referencia_hash (firma_id, referencia_hash, deleted_at),
    KEY idx_pagos_usuario (registrado_por_usuario_id),
    CONSTRAINT fk_pagos_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_pagos_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_pagos_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_pagos_honorario FOREIGN KEY (honorario_id, firma_id) REFERENCES honorarios (id, firma_id),
    CONSTRAINT fk_pagos_usuario FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('finanzas.revelar', 'finanzas', 'revelar', 'Revelar referencias financieras protegidas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('finanzas.revelar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('finanzas.revelar');

DELETE FROM permisos WHERE codigo IN ('finanzas.revelar');
DROP TABLE IF EXISTS pagos;
