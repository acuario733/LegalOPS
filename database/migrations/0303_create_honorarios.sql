-- @up
CREATE TABLE honorarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    caso_id BIGINT UNSIGNED NULL,
    concepto VARCHAR(180) NOT NULL,
    concepto_normalizado VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    monto DECIMAL(14,2) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'COP',
    fecha_acuerdo DATE NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    created_by_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_honorarios_id_firma (id, firma_id),
    KEY idx_honorarios_cliente_estado (firma_id, cliente_id, estado, deleted_at),
    KEY idx_honorarios_caso_estado (firma_id, caso_id, estado, deleted_at),
    KEY idx_honorarios_concepto (firma_id, concepto_normalizado, deleted_at),
    KEY idx_honorarios_creador (created_by_usuario_id),
    CONSTRAINT fk_honorarios_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_honorarios_cliente FOREIGN KEY (cliente_id, firma_id) REFERENCES clientes (id, firma_id),
    CONSTRAINT fk_honorarios_caso FOREIGN KEY (caso_id, firma_id) REFERENCES casos (id, firma_id),
    CONSTRAINT fk_honorarios_creador FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('finanzas.ver', 'finanzas', 'ver', 'Consultar informacion financiera'),
('finanzas.crear', 'finanzas', 'crear', 'Crear movimientos financieros'),
('finanzas.editar', 'finanzas', 'editar', 'Editar movimientos financieros')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('finanzas.ver','finanzas.crear','finanzas.editar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('finanzas.ver','finanzas.crear','finanzas.editar');

DELETE FROM permisos WHERE codigo IN ('finanzas.ver','finanzas.crear','finanzas.editar');
DROP TABLE IF EXISTS honorarios;
