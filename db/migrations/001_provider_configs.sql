CREATE TABLE IF NOT EXISTS provider_configs (
    provider_id TEXT NOT NULL,
    config_key TEXT NOT NULL,
    config_value TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (provider_id, config_key)
);
CREATE TABLE IF NOT EXISTS provider_secrets (
    provider_id TEXT NOT NULL,
    secret_name TEXT NOT NULL,
    secret_ciphertext TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (provider_id, secret_name)
);
