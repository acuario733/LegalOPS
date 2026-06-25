-- @up
ALTER TABLE user_sessions
    ADD COLUMN fingerprint_hash CHAR(64) NULL AFTER session_hash,
    ADD COLUMN revoked_reason VARCHAR(120) NULL AFTER revoked_at;

CREATE INDEX idx_user_sessions_cleanup ON user_sessions (expires_at, revoked_at);

-- @down
DROP INDEX idx_user_sessions_cleanup ON user_sessions;
ALTER TABLE user_sessions
    DROP COLUMN revoked_reason,
    DROP COLUMN fingerprint_hash;

