<?php
/**
 * Fast reseller product loader.
 * Keeps a short server-side cache so public pages do not wait on the upstream
 * provider on every request. The cache lives in /tmp because Render storage is ephemeral.
 */
function fetchResellerProductsFast(string $baseUrl, string $apiKey, int $ttl = 60): array
{
    $baseUrl = rtrim($baseUrl, '/');
    if ($baseUrl === '' || $apiKey === '') {
        return ['products' => [], 'error' => ''];
    }

    $cacheKey = hash('sha256', $baseUrl . '|' . $apiKey);
    $cacheFile = sys_get_temp_dir() . '/logsplug-products-' . $cacheKey . '.json';

    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['products']) && is_array($cached['products'])) {
            return $cached;
        }
    }

    $ch = curl_init($baseUrl . '/api/reseller/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $apiKey, 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $data = $res ? json_decode($res, true) : null;
    if ($code === 200 && is_array($data) && !empty($data['success']) && isset($data['data']) && is_array($data['data'])) {
        $result = ['products' => $data['data'], 'error' => ''];
        @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
        return $result;
    }

    // If the provider is briefly unavailable, serve the last known cache instead of
    // making the whole storefront feel broken.
    if (is_file($cacheFile)) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['products']) && is_array($cached['products'])) {
            $cached['error'] = '';
            return $cached;
        }
    }

    return [
        'products' => [],
        'error' => is_array($data) && !empty($data['message'])
            ? (string) $data['message']
            : ($curlError ? 'Services are temporarily unavailable.' : 'Services are temporarily unavailable.'),
    ];
}
