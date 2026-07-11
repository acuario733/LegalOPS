-- @up
ALTER TABLE firma_planes
    ADD COLUMN proration_policy VARCHAR(30) NOT NULL DEFAULT 'manual_review' AFTER auto_suspend_at,
    ADD COLUMN proration_note VARCHAR(500) NULL AFTER proration_policy,
    ADD KEY idx_firma_planes_proration (estado, proration_policy);

-- @down
ALTER TABLE firma_planes
    DROP INDEX idx_firma_planes_proration,
    DROP COLUMN proration_note,
    DROP COLUMN proration_policy;
