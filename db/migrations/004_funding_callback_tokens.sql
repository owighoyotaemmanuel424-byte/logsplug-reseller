ALTER TABLE fund_requests ADD COLUMN IF NOT EXISTS callback_token_hash TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS ux_fund_callback_token_hash ON fund_requests(callback_token_hash) WHERE callback_token_hash IS NOT NULL;
