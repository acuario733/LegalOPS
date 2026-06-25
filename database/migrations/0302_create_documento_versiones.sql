-- @up
CREATE TABLE documento_versiones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id BIGINT UNSIGNED NOT NULL,
    documento_id BIGINT UNSIGNED NOT NULL,
    version_numero INT UNSIGNED NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_fisico VARCHAR(160) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime_declarado VARCHAR(120) NULL,
    mime_detectado VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    uploaded_by_usuario_id BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_documento_version (firma_id, documento_id, version_numero),
    KEY idx_documento_versiones_documento (firma_id, documento_id, created_at),
    KEY idx_documento_versiones_checksum (checksum_sha256),
    KEY idx_documento_versiones_usuario (uploaded_by_usuario_id),
    CONSTRAINT fk_documento_versiones_documento FOREIGN KEY (documento_id, firma_id) REFERENCES documentos (id, firma_id),
    CONSTRAINT fk_documento_versiones_firma FOREIGN KEY (firma_id) REFERENCES firmas (id),
    CONSTRAINT fk_documento_versiones_usuario FOREIGN KEY (uploaded_by_usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE documentos
    ADD CONSTRAINT fk_documentos_current_version FOREIGN KEY (current_version_id) REFERENCES documento_versiones (id);

INSERT INTO permisos (codigo, modulo, accion, descripcion) VALUES
('documentos.versionar', 'documentos', 'versionar', 'Crear versiones de documentos'),
('documentos.descargar', 'documentos', 'descargar', 'Descargar documentos privados')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), modulo = VALUES(modulo), accion = VALUES(accion);

INSERT INTO rol_permiso (firma_id, rol_id, permiso_id, created_at)
SELECT r.firma_id, r.id, p.id, CURRENT_TIMESTAMP(6)
FROM roles r
INNER JOIN permisos p ON p.codigo IN ('documentos.versionar','documentos.descargar')
WHERE r.codigo = 'administrador' AND r.is_protected = 1 AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at = rol_permiso.created_at;

-- @down
ALTER TABLE documentos DROP FOREIGN KEY fk_documentos_current_version;

DELETE rp FROM rol_permiso rp
INNER JOIN permisos p ON p.id = rp.permiso_id
WHERE p.codigo IN ('documentos.versionar','documentos.descargar');

DELETE FROM permisos WHERE codigo IN ('documentos.versionar','documentos.descargar');
DROP TABLE IF EXISTS documento_versiones;
