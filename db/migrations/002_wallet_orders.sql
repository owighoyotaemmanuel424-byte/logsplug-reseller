CREATE TABLE IF NOT EXISTS wallet_transactions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL CHECK (type IN ('credit','debit','refund','adjustment')),
    amount_kobo BIGINT NOT NULL CHECK (amount_kobo > 0),
    reference TEXT NOT NULL,
    provider TEXT,
    provider_ref TEXT,
    description TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_wallet_provider_ref
ON wallet_transactions(provider, provider_ref)
WHERE provider IS NOT NULL AND provider_ref IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_wallet_transactions_user_created
ON wallet_transactions(user_id, created_at DESC);

ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance NUMERIC(14,2) NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS provider TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS provider_ref TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS status TEXT NOT NULL DEFAULT 'pending';
ALTER TABLE orders ADD COLUMN IF NOT EXISTS idempotency_key TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS ux_orders_provider_ref
ON orders(provider, provider_ref)
WHERE provider IS NOT NULL AND provider_ref IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_orders_idempotency
ON orders(user_id, idempotency_key)
WHERE idempotency_key IS NOT NULL;
