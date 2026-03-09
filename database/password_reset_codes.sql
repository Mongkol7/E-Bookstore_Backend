CREATE TABLE IF NOT EXISTS password_reset_codes (
    id BIGSERIAL PRIMARY KEY,
    email TEXT NOT NULL,
    user_type TEXT NOT NULL,
    user_id BIGINT NOT NULL,
    code_hash TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    used_at TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS idx_password_reset_codes_lookup
    ON password_reset_codes (email, used_at, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_password_reset_codes_user
    ON password_reset_codes (user_type, user_id, used_at);
