-- @up
ALTER TABLE documentos
    ADD COLUMN texto_extraido LONGTEXT NULL AFTER visible_portal,
    ADD COLUMN texto_extraido_at DATETIME(6) NULL AFTER texto_extraido,
    ADD COLUMN firma_estado ENUM('sin_firma','pendiente','enviado','completado','rechazado') NOT NULL DEFAULT 'sin_firma' AFTER texto_extraido_at,
    ADD COLUMN docusign_envelope_id VARCHAR(100) NULL AFTER firma_estado,
    ADD COLUMN firma_solicitada_at DATETIME(6) NULL AFTER docusign_envelope_id,
    ADD COLUMN firma_completada_at DATETIME(6) NULL AFTER firma_solicitada_at,
    ADD UNIQUE KEY uq_documentos_docusign_envelope (docusign_envelope_id),
    ADD FULLTEXT KEY ftx_documentos_texto (titulo,texto_extraido);

ALTER TABLE exportaciones
    ADD COLUMN s3_key VARCHAR(500) NULL AFTER archivo_path;

INSERT INTO permisos (codigo,modulo,accion,descripcion) VALUES
('documentos.firmar','documentos','firmar','Enviar documentos a firma electronica'),
('auditoria.exportar','auditoria','exportar','Exportar audit log a CSV')
ON DUPLICATE KEY UPDATE descripcion=VALUES(descripcion),modulo=VALUES(modulo),accion=VALUES(accion);

INSERT INTO rol_permiso (firma_id,rol_id,permiso_id,created_at)
SELECT r.firma_id,r.id,p.id,CURRENT_TIMESTAMP(6)
FROM roles r INNER JOIN permisos p ON p.codigo IN ('documentos.firmar','auditoria.exportar')
WHERE r.codigo='administrador' AND r.deleted_at IS NULL
ON DUPLICATE KEY UPDATE created_at=rol_permiso.created_at;

-- @down
DELETE rp FROM rol_permiso rp INNER JOIN permisos p ON p.id=rp.permiso_id WHERE p.codigo IN ('documentos.firmar','auditoria.exportar');
DELETE FROM permisos WHERE codigo IN ('documentos.firmar','auditoria.exportar');
ALTER TABLE exportaciones DROP COLUMN s3_key;
ALTER TABLE documentos
    DROP INDEX uq_documentos_docusign_envelope,
    DROP INDEX ftx_documentos_texto,
    DROP COLUMN texto_extraido,
    DROP COLUMN texto_extraido_at,
    DROP COLUMN firma_estado,
    DROP COLUMN docusign_envelope_id,
    DROP COLUMN firma_solicitada_at,
    DROP COLUMN firma_completada_at;
