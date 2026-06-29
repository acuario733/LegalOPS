-- @up
ALTER TABLE usuarios
    ADD COLUMN tarifa_hora DECIMAL(10,2) NULL AFTER cargo;

-- @down
ALTER TABLE usuarios
    DROP COLUMN tarifa_hora;
