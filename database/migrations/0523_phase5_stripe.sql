-- @up
ALTER TABLE honorarios
    ADD COLUMN stripe_payment_intent_id VARCHAR(100) NULL AFTER anulado_por_usuario_id,
    ADD COLUMN stripe_status VARCHAR(50) NULL AFTER stripe_payment_intent_id,
    ADD COLUMN pago_token VARCHAR(64) NULL AFTER stripe_status,
    ADD COLUMN pago_token_expires_at DATETIME(6) NULL AFTER pago_token,
    ADD UNIQUE KEY uq_honorarios_stripe_intent (stripe_payment_intent_id),
    ADD UNIQUE KEY uq_honorarios_pago_token (pago_token);

ALTER TABLE pagos
    ADD COLUMN stripe_charge_id VARCHAR(100) NULL AFTER referencia_hash,
    ADD COLUMN reembolsado_at DATETIME(6) NULL AFTER stripe_charge_id,
    ADD COLUMN reembolso_monto DECIMAL(15,2) NULL AFTER reembolsado_at,
    ADD COLUMN stripe_refund_id VARCHAR(100) NULL AFTER reembolso_monto,
    ADD UNIQUE KEY uq_pagos_stripe_charge (stripe_charge_id);

-- @down
ALTER TABLE pagos
    DROP INDEX uq_pagos_stripe_charge,
    DROP COLUMN stripe_charge_id,
    DROP COLUMN reembolsado_at,
    DROP COLUMN reembolso_monto,
    DROP COLUMN stripe_refund_id;
ALTER TABLE honorarios
    DROP INDEX uq_honorarios_stripe_intent,
    DROP INDEX uq_honorarios_pago_token,
    DROP COLUMN stripe_payment_intent_id,
    DROP COLUMN stripe_status,
    DROP COLUMN pago_token,
    DROP COLUMN pago_token_expires_at;
