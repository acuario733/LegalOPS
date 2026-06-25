-- @up
ALTER TABLE password_resets
    ADD COLUMN requested_ip VARCHAR(45) NULL AFTER token_hash,
    ADD COLUMN requested_user_agent VARCHAR(255) NULL AFTER requested_ip;

CREATE INDEX idx_password_resets_expiration ON password_resets (expires_at, used_at);
CREATE INDEX idx_login_attempts_cleanup ON login_attempts (attempted_at);

-- @down
DROP INDEX idx_login_attempts_cleanup ON login_attempts;
DROP INDEX idx_password_resets_expiration ON password_resets;
ALTER TABLE password_resets
    DROP COLUMN requested_user_agent,
    DROP COLUMN requested_ip;

