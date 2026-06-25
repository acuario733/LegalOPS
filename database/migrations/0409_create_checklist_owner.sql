-- @up
CREATE TABLE checklist_owner (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NULL,
    titulo VARCHAR(180) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    decision VARCHAR(30) NULL,
    decision_observacion TEXT NULL,
    decidido_por_usuario_id BIGINT UNSIGNED NULL,
    decidido_at DATETIME(6) NULL,
    created_by_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_checklist_owner_estado (firma_id, estado, created_at),
    CONSTRAINT fk_checklist_owner_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_checklist_owner_decidido_por FOREIGN KEY (decidido_por_usuario_id) REFERENCES usuarios (id),
    CONSTRAINT fk_checklist_owner_creado_por FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_owner_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    checklist_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    observacion TEXT NULL,
    evidencia VARCHAR(500) NULL,
    evaluado_por_usuario_id BIGINT UNSIGNED NULL,
    evaluado_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_checklist_item_codigo (checklist_id, codigo),
    KEY idx_checklist_items_estado (checklist_id, estado),
    CONSTRAINT fk_checklist_items_checklist FOREIGN KEY (checklist_id) REFERENCES checklist_owner (id),
    CONSTRAINT fk_checklist_items_usuario FOREIGN KEY (evaluado_por_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('checklist.ver', 'checklist', 'ver', 'Consultar checklist owner'),
('checklist.evaluar', 'checklist', 'evaluar', 'Evaluar items de checklist owner'),
('checklist.aprobar', 'checklist', 'aprobar', 'Emitir decision final de checklist owner')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

-- @down
DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('checklist.ver','checklist.evaluar','checklist.aprobar');

DELETE FROM permisos WHERE codigo IN ('checklist.ver','checklist.evaluar','checklist.aprobar');
DROP TABLE IF EXISTS checklist_owner_items;
DROP TABLE IF EXISTS checklist_owner;
