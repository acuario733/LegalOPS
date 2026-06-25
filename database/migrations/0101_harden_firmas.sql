-- @up
ALTER TABLE firmas
    ADD COLUMN suspended_at DATETIME(6) NULL AFTER timezone,
    ADD COLUMN suspension_reason VARCHAR(255) NULL AFTER suspended_at;

CREATE INDEX idx_firmas_nombre ON firmas (nombre, deleted_at);

-- @down
DROP INDEX idx_firmas_nombre ON firmas;
ALTER TABLE firmas
    DROP COLUMN suspension_reason,
    DROP COLUMN suspended_at;

