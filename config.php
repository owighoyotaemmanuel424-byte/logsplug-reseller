<?php
// Runtime configuration. Set these as server environment variables in production.
define('RESELLER_API_KEY', trim((string) (getenv('RESELLER_API_KEY') ?: '')));
define('API_BASE_URL', rtrim((string) (getenv('API_BASE_URL') ?: 'https://logspanel.com/api/v1'), '/'));
define('MARKUP_PERCENT', (float) (getenv('MARKUP_PERCENT') ?: 10));
define('SITE_TITLE', getenv('SITE_TITLE') ?: 'My Reseller Store');
define('BUSINESS_NAME', getenv('BUSINESS_NAME') ?: 'My Reseller Store');
define('LOGO_URL', getenv('LOGO_URL') ?: '');
define('DATABASE_URL', trim((string) (getenv('DATABASE_URL') ?: '')));
define('AUTH_SESSION_SECRET', trim((string) (getenv('AUTH_SESSION_SECRET') ?: '')));
define('NEON_AUTH_URL', rtrim(trim((string) (getenv('NEON_AUTH_URL') ?: '')), '/'));
define('APP_URL', rtrim(trim((string) (getenv('APP_URL') ?: 'https://logsplug-reseller-php.onrender.com')), '/'));
define('ADMIN_DEFAULT_EMAIL', trim((string) (getenv('ADMIN_DEFAULT_EMAIL') ?: '')));
define('ADMIN_DEFAULT_PASSWORD', (string) (getenv('ADMIN_DEFAULT_PASSWORD') ?: ''));
define('ADMIN_DEFAULT_NAME', trim((string) (getenv('ADMIN_DEFAULT_NAME') ?: 'Administrator')));
define('ADMIN_MASTER_KEY', (string) (getenv('ADMIN_MASTER_KEY') ?: ''));
define('ADMIN_NOTIFICATION_EMAIL', trim((string) (getenv('ADMIN_NOTIFICATION_EMAIL') ?: '')));
define('ADMIN_SESSION_SECRET', (string) (getenv('ADMIN_SESSION_SECRET') ?: ''));
define('SPRINTPAY_ENABLED', filter_var(getenv('SPRINTPAY_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('SPRINTPAY_MERCHANT_ID', getenv('SPRINTPAY_MERCHANT_ID') ?: '');
define('SPRINTPAY_CALLBACK_URL', getenv('SPRINTPAY_CALLBACK_URL') ?: '');

// Admin-managed provider settings override environment defaults when present.
try {
    $providerPdo = createDatabaseConnection();
    if ($providerPdo) {
        $st = $providerPdo->prepare('SELECT key, value FROM settings WHERE key IN (?, ?)');
        $st->execute(['provider_api_base_url', 'provider_api_key']);
        $providerSettings = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) $providerSettings[(string)$row['key']] = (string)$row['value'];
        if (!empty($providerSettings['provider_api_base_url'])) define('API_BASE_URL', rtrim($providerSettings['provider_api_base_url'], '/'));
        if (array_key_exists('provider_api_key', $providerSettings)) define('RESELLER_API_KEY', $providerSettings['provider_api_key']);
    }
} catch (Throwable $e) { error_log('Provider settings lookup warning: ' . $e->getMessage()); }


function createDatabaseConnection(): ?PDO
{
    $url = defined('DATABASE_URL') ? trim((string) DATABASE_URL) : '';
    if ($url === '') return null;

    try {
        if (preg_match('/^postgres(?:ql)?:\/\//i', $url)) {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host'])) throw new RuntimeException('Invalid PostgreSQL DATABASE_URL.');
            $host = $parts['host'];
            $port = isset($parts['port']) ? (int) $parts['port'] : 5432;
            $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
            $user = isset($parts['user']) ? urldecode($parts['user']) : '';
            $password = isset($parts['pass']) ? urldecode($parts['pass']) : '';
            if ($dbname === '' || $user === '') throw new RuntimeException('DATABASE_URL is missing database name or username.');
            $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';sslmode=require';
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return new PDO($url, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}
