<?php
declare(strict_types=1);

define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_URL', rtrim((string)(getenv('APP_URL') ?: 'https://logsplug-reseller-php.onrender.com'), '/'));
define('DATABASE_URL', trim((string)(getenv('DATABASE_URL') ?: '')));
define('DATABASE_URL_DIRECT', trim((string)(getenv('DATABASE_URL_DIRECT') ?: '')));
define('APP_ENCRYPTION_KEY', trim((string)(getenv('APP_ENCRYPTION_KEY') ?: '')));
define('CRON_SECRET', trim((string)(getenv('CRON_SECRET') ?: '')));
define('SESSION_SECRET', trim((string)(getenv('SESSION_SECRET') ?: '')));
define('MARKUP_PERCENT', trim((string)(getenv('MARKUP_PERCENT') ?: '0')));
define('SITE_TITLE', getenv('SITE_TITLE') ?: 'LogsPlug Reseller');
define('BUSINESS_NAME', getenv('BUSINESS_NAME') ?: 'LogsPlug Reseller');
define('LOGO_URL', getenv('LOGO_URL') ?: '');

function createDatabaseConnection(bool $direct = false): PDO
{
    $url = trim((string)($direct ? DATABASE_URL_DIRECT : DATABASE_URL));
    if ($url === '') throw new RuntimeException('Database connection is not configured.');
    if (!preg_match('#^postgres(?:ql)?://#i', $url)) throw new RuntimeException('PostgreSQL is required.');
    $parts=parse_url($url);
    if(!is_array($parts)||empty($parts['host'])||empty($parts['user'])||empty($parts['path'])) throw new RuntimeException('Invalid PostgreSQL URL.');
    $host=(string)$parts['host'];
    if(!$direct && strpos($host,'-pooler')===false) throw new RuntimeException('DATABASE_URL must use a Neon pooled hostname.');
    if($direct && strpos($host,'-pooler')!==false) throw new RuntimeException('DATABASE_URL_DIRECT must use a non-pooled hostname.');
    $dsn='pgsql:host='.$host.';port='.(int)($parts['port']??5432).';dbname='.ltrim((string)$parts['path'],'/').';sslmode=require';
    return new PDO($dsn,urldecode((string)$parts['user']),urldecode((string)($parts['pass']??'')),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>true,
        PDO::ATTR_PERSISTENT=>false,
    ]);
}
