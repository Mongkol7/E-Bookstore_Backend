-- Purchase alert outbox queue
-- Run this SQL on local PostgreSQL and Railway PostgreSQL.

CREATE TABLE IF NOT EXISTS purchase_alert_outbox (
    id BIGSERIAL PRIMARY KEY,
    order_id TEXT NOT NULL,
    order_number TEXT NOT NULL,
    user_type TEXT NOT NULL,
    user_id BIGINT NOT NULL,
    payload JSONB NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    attempt_count INT NOT NULL DEFAULT 0,
    next_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_error TEXT,
    sent_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_purchase_alert_status
        CHECK (status IN ('pending', 'sending', 'sent', 'failed'))
);

CREATE INDEX IF NOT EXISTS idx_purchase_alert_outbox_status_next_attempt
ON purchase_alert_outbox (status, next_attempt_at);

CREATE INDEX IF NOT EXISTS idx_purchase_alert_outbox_order_id
ON purchase_alert_outbox (order_id);

CREATE INDEX IF NOT EXISTS idx_purchase_alert_outbox_order_number
ON purchase_alert_outbox (order_number);

CREATE OR REPLACE FUNCTION set_purchase_alert_outbox_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_purchase_alert_outbox_updated_at ON purchase_alert_outbox;

CREATE TRIGGER trg_purchase_alert_outbox_updated_at
BEFORE UPDATE ON purchase_alert_outbox
FOR EACH ROW
EXECUTE FUNCTION set_purchase_alert_outbox_updated_at();
