<?php
// Runtime configuration. Set these as server environment variables in production.
define('RESELLER_API_KEY', getenv('RESELLER_API_KEY') ?: '');
define('API_BASE_URL', rtrim(getenv('API_BASE_URL') ?: 'http://127.0.0.1:8001', '/'));
define('MARKUP_PERCENT', (float) (getenv('MARKUP_PERCENT') ?: 10));
define('SITE_TITLE', getenv('SITE_TITLE') ?: 'My Reseller Store');
define('BUSINESS_NAME', getenv('BUSINESS_NAME') ?: 'My Reseller Store');
define('LOGO_URL', getenv('LOGO_URL') ?: '');
define('DATABASE_URL', trim((string) (getenv('DATABASE_URL') ?: '')));
define('SPRINTPAY_ENABLED', filter_var(getenv('SPRINTPAY_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('SPRINTPAY_MERCHANT_ID', getenv('SPRINTPAY_MERCHANT_ID') ?: '');
define('SPRINTPAY_CALLBACK_URL', getenv('SPRINTPAY_CALLBACK_URL') ?: '');
