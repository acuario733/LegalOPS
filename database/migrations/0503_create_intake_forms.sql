-- @up
CREATE TABLE intake_forms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    campos JSON NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    mensaje_exito TEXT NULL,
    notificar_emails TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_forms_slug (slug),
    UNIQUE KEY uq_intake_forms_id_firma (id, firma_id),
    KEY idx_intake_forms_firma (firma_id),
    KEY idx_intake_forms_slug (slug),
    CONSTRAINT fk_intake_forms_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_form_id BIGINT UNSIGNED NOT NULL,
    firma_id BIGINT UNSIGNED NOT NULL,
    datos JSON NOT NULL,
    prospecto_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    procesado TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intake_submissions_form (firma_id, intake_form_id),
    KEY idx_intake_submissions_procesado (firma_id, procesado),
    KEY idx_intake_submissions_prospecto (prospecto_id, firma_id),
    CONSTRAINT fk_intake_submissions_firma
        FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_intake_submissions_form_firma
        FOREIGN KEY (intake_form_id, firma_id) REFERENCES intake_forms (id, firma_id),
    CONSTRAINT fk_intake_submissions_prospecto_firma
        FOREIGN KEY (prospecto_id, firma_id) REFERENCES prospectos (id, firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('intake.ver', 'intake', 'ver', 'Consultar formularios de captacion'),
('intake.crear', 'intake', 'crear', 'Crear formularios de captacion'),
('intake.editar', 'intake', 'editar', 'Editar formularios de captacion'),
('intake.eliminar', 'intake', 'eliminar', 'Eliminar formularios de captacion')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('intake.ver', 'intake.crear', 'intake.editar', 'intake.eliminar')
WHERE r.codigo = 'administrador' AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('intake.ver', 'intake.crear', 'intake.editar')
WHERE r.codigo = 'abogado' AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('intake.ver', 'intake.crear', 'intake.editar', 'intake.eliminar');

DELETE FROM permisos WHERE codigo IN ('intake.ver', 'intake.crear', 'intake.editar', 'intake.eliminar');
DROP TABLE IF EXISTS intake_submissions;
DROP TABLE IF EXISTS intake_forms;
