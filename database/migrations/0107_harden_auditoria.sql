-- @up
ALTER TABLE auditoria ADD COLUMN correlation_id VARCHAR(64) NULL AFTER severidad;
CREATE INDEX idx_auditoria_entidad ON auditoria (firma_id, entidad_tipo, entidad_id, created_at);
CREATE INDEX idx_auditoria_correlacion ON auditoria (correlation_id);

-- @down
DROP INDEX idx_auditoria_correlacion ON auditoria;
DROP INDEX idx_auditoria_entidad ON auditoria;
ALTER TABLE auditoria DROP COLUMN correlation_id;

