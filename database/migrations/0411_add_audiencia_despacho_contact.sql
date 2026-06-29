-- @up
ALTER TABLE audiencias
    ADD COLUMN juez_responsable VARCHAR(180) NULL AFTER despacho,
    ADD COLUMN despacho_contacto VARCHAR(255) NULL AFTER juez_responsable;

-- @down
ALTER TABLE audiencias
    DROP COLUMN despacho_contacto,
    DROP COLUMN juez_responsable;
