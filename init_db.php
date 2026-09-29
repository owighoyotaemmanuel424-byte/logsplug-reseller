<?php
/**
 * Initialize the PostgreSQL schema used by the reseller application.
 * Safe to run on every request. A PostgreSQL advisory lock prevents
 * concurrent Render requests from racing during first-time schema creation.
 */
if (!file_exists(__DIR__ . '/config.php')) {
    return;
}
require_once __DIR__ . '/config.php';

if (!function_exists('createDatabaseConnection')) {
    return;
}

try {
    $pdo = createDatabaseConnection();
    if ($pdo === null) {
        return;
    }

    // Prevent concurrent PHP requests from creating the same PostgreSQL
    // tables/indexes at the same time.
    $pdo->exec('SELECT pg_advisory_lock(48392017)');

    try {
        $statements = [
            "CREATE TABLE IF NOT EXISTS users (
                id BIGSERIAL PRIMARY KEY,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                name TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS wallets (
                user_id BIGINT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                balance NUMERIC(18,2) NOT NULL DEFAULT 0,
                updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )",
            "CREATE TABLE IF NOT EXISTS orders (
                id BIGSERIAL PRIMARY KEY,
                user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
                product_id INTEGER NOT NULL,
                product_name TEXT NOT NULL,
                qty INTEGER NOT NULL,
                unit_price NUMERIC(18,2) NOT NULL,
                total_amount NUMERIC(18,2) NOT NULL,
                api_order_id TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reported_at TIMESTAMPTZ,
                report_reason TEXT,
                replacement_status TEXT,
                replacement_note TEXT,
                replaced_at TIMESTAMPTZ,
                product_details TEXT
            )",
            "CREATE TABLE IF NOT EXISTS fund_requests (
                id BIGSERIAL PRIMARY KEY,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                amount NUMERIC(18,2) NOT NULL,
                reference TEXT UNIQUE NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMPTZ
            )",
            "CREATE TABLE IF NOT EXISTS markup_requests (
                id BIGSERIAL PRIMARY KEY,
                requested_percent NUMERIC(8,2) NOT NULL,
                note TEXT,
                created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "CREATE INDEX IF NOT EXISTS idx_orders_user_created ON orders(user_id, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_orders_reported ON orders(reported_at DESC) WHERE reported_at IS NOT NULL",
            "CREATE INDEX IF NOT EXISTS idx_fund_requests_user_created ON fund_requests(user_id, created_at DESC)",
            "CREATE INDEX IF NOT EXISTS idx_fund_requests_status ON fund_requests(status)",
            "CREATE INDEX IF NOT EXISTS idx_markup_requests_created ON markup_requests(created_at DESC)",
        ];

        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    } finally {
        $pdo->exec('SELECT pg_advisory_unlock(48392017)');
    }
} catch (Throwable $e) {
    error_log('Database schema initialization failed: ' . $e->getMessage());
}
