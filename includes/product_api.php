<?php
require_once __DIR__ . '/logspanel_api.php';

/**
 * Backward-compatible product loader for the existing reseller UI.
 * Logspanel is now the upstream provider.
 */
function fetchResellerProductsFast(string $baseUrl, string $apiKey, int $ttl = 60): array
{
    return logspanelFetchCatalog();
}
