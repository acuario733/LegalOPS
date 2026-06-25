-- @up
ALTER TABLE documentos_legales ADD COLUMN published_by_usuario_id BIGINT UNSIGNED NULL AFTER published_at;
ALTER TABLE documentos_legales
    ADD CONSTRAINT fk_documentos_legales_publicador FOREIGN KEY (published_by_usuario_id) REFERENCES usuarios (id);

ALTER TABLE aceptaciones_legales ADD COLUMN evidence_hash CHAR(64) NOT NULL AFTER user_agent;
CREATE INDEX idx_aceptaciones_documento ON aceptaciones_legales (documento_legal_id, accepted_at);

-- @down
CREATE INDEX idx_aceptaciones_documento_fk ON aceptaciones_legales (documento_legal_id);
DROP INDEX idx_aceptaciones_documento ON aceptaciones_legales;
ALTER TABLE aceptaciones_legales DROP COLUMN evidence_hash;
ALTER TABLE documentos_legales DROP FOREIGN KEY fk_documentos_legales_publicador;
ALTER TABLE documentos_legales DROP COLUMN published_by_usuario_id;
