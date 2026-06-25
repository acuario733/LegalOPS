-- @up
CREATE TABLE onboarding_progreso (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    paso VARCHAR(80) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    completed_at DATETIME(6) NULL,
    completed_by_usuario_id BIGINT UNSIGNED NULL,
    metadata_json JSON NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_onboarding_firma_paso (firma_id, paso),
    KEY idx_onboarding_estado (firma_id, estado),
    CONSTRAINT fk_onboarding_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_onboarding_usuario FOREIGN KEY (completed_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('onboarding.ver', 'onboarding', 'ver', 'Consultar progreso de activacion'),
('onboarding.administrar', 'onboarding', 'administrar', 'Administrar progreso de activacion')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('onboarding.ver','onboarding.administrar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('onboarding.ver','onboarding.administrar');

DELETE FROM permisos WHERE codigo IN ('onboarding.ver','onboarding.administrar');
DROP TABLE IF EXISTS onboarding_progreso;
