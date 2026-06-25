-- @up
ALTER TABLE catalogos ADD COLUMN scope_key VARCHAR(240) NULL AFTER codigo;

UPDATE catalogos
SET scope_key = CASE
    WHEN firma_id IS NULL THEN CONCAT('global:', codigo)
    ELSE CONCAT('firma:', firma_id, ':', codigo)
END
WHERE scope_key IS NULL;

ALTER TABLE catalogos MODIFY scope_key VARCHAR(240) NOT NULL;
CREATE UNIQUE INDEX uq_catalogos_scope_key ON catalogos (scope_key);

-- @down
DROP INDEX uq_catalogos_scope_key ON catalogos;
ALTER TABLE catalogos DROP COLUMN scope_key;

