<?php
/**
 * Initialize SQLite DB for end-user accounts and wallets.
 * Call once (e.g. from auth_helpers.php) or run manually.
 */
if (!file_exists(__DIR__ . '/config.php')) {
    return;
}
require_once __DIR__ . '/config.php';
$dbPath = defined('DB_PATH') ? DB_PATH : '';
if ($dbPath === '' || $dbPath === false) {
    return;
}
$dir = dirname($dbPath);
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            user_id INTEGER PRIMARY KEY,
            balance REAL NOT NULL DEFAULT 0,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            product_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            qty INTEGER NOT NULL,
            unit_price REAL NOT NULL,
            total_amount REAL NOT NULL,
            api_order_id TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            reported_at TEXT,
            report_reason TEXT,
            replacement_status TEXT,
            replacement_note TEXT,
            replaced_at TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    $newOrderCols = ['reported_at TEXT', 'report_reason TEXT', 'replacement_status TEXT', 'replacement_note TEXT', 'replaced_at TEXT', 'product_details TEXT'];
    foreach ($newOrderCols as $colDef) {
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN " . $colDef);
        } catch (Exception $e) {
            /* column may already exist */
        }
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS fund_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            reference TEXT UNIQUE NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            completed_at TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS markup_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            requested_percent REAL NOT NULL,
            note TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    // Log or display in dev only
}
