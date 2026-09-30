<?php
/**
 * Fast reseller product loader.
 * Keeps a short server-side cache so public pages do not wait on the upstream
 * provider on every request. The cache lives in /tmp because Render storage is ephemeral.
 */
function fetchResellerProductsFast(string $baseUrl, string $apiKey, int $ttl = 60): array
{
    $baseUrl = rtrim($baseUrl, '/');
    if ($baseUrl === '') return ['products' => [], 'error' => 'Provider API URL is not configured.'];
    if ($apiKey === '') return ['products' => [], 'error' => 'Provider API key is not configured.'];

    $cacheKey = hash('sha256', $baseUrl . '|' . $apiKey);
    $cacheFile = sys_get_temp_dir() . '/logsplug-products-' . $cacheKey . '.json';

    if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['products']) && is_array($cached['products'])) return $cached;
    }

    $appUrl = defined('APP_URL') ? rtrim((string) APP_URL, '/') : '';
    $headers = [
        'X-Api-Key: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
        'User-Agent: LogsPlug-Reseller/1.0 (+server-to-server)',
    ];
    if ($appUrl !== '') {
        $headers[] = 'Origin: ' . $appUrl;
        $headers[] = 'Referer: ' . $appUrl . '/';
    }

    $ch = curl_init($baseUrl . '/api/reseller/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $data = $res ? json_decode($res, true) : null;
    if ($code >= 200 && $code < 300 && is_array($data)) {
        $providerProducts = null;
        if (isset($data['data']) && is_array($data['data'])) $providerProducts = $data['data'];
        elseif (isset($data['products']) && is_array($data['products'])) $providerProducts = $data['products'];
        elseif (isset($data['services']) && is_array($data['services'])) $providerProducts = $data['services'];

        if ($providerProducts !== null && (!array_key_exists('success', $data) || !empty($data['success']))) {
            $result = ['products' => $providerProducts, 'error' => ''];
            @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
            return $result;
        }
    }

    if (is_file($cacheFile)) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['products']) && is_array($cached['products'])) {
            $cached['error'] = '';
            return $cached;
        }
    }

    $providerMessage = is_array($data) && !empty($data['message']) ? (string) $data['message'] : '';
    $providerCode = is_array($data) && !empty($data['code']) ? (string) $data['code'] : '';
    $rawBody = is_string($res) ? trim(preg_replace('/\s+/', ' ', $res)) : '';

    if ($code >= 400) {
        $detail = $providerCode !== '' ? $providerCode . ': ' : '';
        if ($providerMessage === '' && $rawBody !== '') {
            $rawBody = substr($rawBody, 0, 300);
            if (stripos($rawBody, 'Just a moment') !== false || stripos($rawBody, 'cf-chl-') !== false || stripos($rawBody, 'Cloudflare') !== false) {
                $providerMessage = 'Cloudflare challenge blocked the server-to-server API request. The provider must allow /api/reseller/* for this integration.';
            } else {
                $providerMessage = 'Provider response: ' . $rawBody;
            }
        }
        $error = $detail . ($providerMessage !== '' ? $providerMessage : 'Provider API returned HTTP ' . $code . '.');
    } elseif ($curlError !== '') {
        $error = 'Unable to reach the provider API: ' . $curlError;
    } else {
        $error = 'Provider API returned an invalid response.';
    }

    error_log('Provider API failure: HTTP ' . $code . '; content-type=' . $contentType . '; URL=' . $baseUrl . '/api/reseller/products; error=' . $error);
    return ['products' => [], 'error' => $error];
}
