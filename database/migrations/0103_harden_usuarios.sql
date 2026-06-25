-- @up
ALTER TABLE usuarios
    ADD COLUMN email_scope VARCHAR(320) NULL AFTER email_normalizado,
    ADD COLUMN invited_at DATETIME(6) NULL AFTER must_change_password,
    ADD COLUMN deactivated_at DATETIME(6) NULL AFTER invited_at;

UPDATE usuarios
SET email_scope = CASE
    WHEN firma_id IS NULL THEN CONCAT('global:', email_normalizado)
    ELSE CONCAT('firma:', firma_id, ':', email_normalizado)
END
WHERE email_scope IS NULL;

ALTER TABLE usuarios MODIFY email_scope VARCHAR(320) NOT NULL;
CREATE UNIQUE INDEX uq_usuarios_email_scope ON usuarios (email_scope);

-- @down
DROP INDEX uq_usuarios_email_scope ON usuarios;
ALTER TABLE usuarios
    DROP COLUMN deactivated_at,
    DROP COLUMN invited_at,
    DROP COLUMN email_scope;

