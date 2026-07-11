-- @up
ALTER TABLE firma_planes
    ADD COLUMN billing_period VARCHAR(20) NOT NULL DEFAULT 'monthly' AFTER renews_at,
    ADD COLUMN billing_anchor_day TINYINT UNSIGNED NULL AFTER billing_period,
    ADD COLUMN trial_started_at DATETIME(6) NULL AFTER billing_anchor_day,
    ADD COLUMN trial_ends_at DATETIME(6) NULL AFTER trial_started_at,
    ADD COLUMN payment_due_at DATETIME(6) NULL AFTER trial_ends_at,
    ADD COLUMN grace_ends_at DATETIME(6) NULL AFTER payment_due_at,
    ADD COLUMN auto_suspend_at DATETIME(6) NULL AFTER grace_ends_at,
    ADD KEY idx_firma_planes_billing_due (estado, payment_due_at, auto_suspend_at),
    ADD KEY idx_firma_planes_trial (estado, trial_ends_at);

UPDATE firma_planes
SET billing_period = 'monthly',
    billing_anchor_day = COALESCE(billing_anchor_day, DAY(starts_at)),
    renews_at = COALESCE(renews_at, DATE_ADD(starts_at, INTERVAL 1 MONTH))
WHERE estado = 'activo'
  AND ends_at IS NULL;

-- @down
ALTER TABLE firma_planes
    DROP INDEX idx_firma_planes_billing_due,
    DROP INDEX idx_firma_planes_trial,
    DROP COLUMN auto_suspend_at,
    DROP COLUMN grace_ends_at,
    DROP COLUMN payment_due_at,
    DROP COLUMN trial_ends_at,
    DROP COLUMN trial_started_at,
    DROP COLUMN billing_anchor_day,
    DROP COLUMN billing_period;
