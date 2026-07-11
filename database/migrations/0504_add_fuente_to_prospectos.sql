-- @up
ALTER TABLE prospectos
    ADD COLUMN fuente_referencia VARCHAR(200) NULL AFTER fuente,
    ADD KEY idx_prospectos_fuente_referencia (firma_id, fuente, fuente_referencia);

ALTER TABLE prospectos
    ALTER COLUMN fuente SET DEFAULT 'manual';

-- @down
ALTER TABLE prospectos
    ALTER COLUMN fuente DROP DEFAULT;

ALTER TABLE prospectos
    DROP INDEX idx_prospectos_fuente_referencia,
    DROP COLUMN fuente_referencia;
