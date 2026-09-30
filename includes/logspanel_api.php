<?php
/**
 * Logspanel API v1 adapter.
 * API keys stay server-side. The adapter normalizes Logspanel's catalog into
 * the legacy reseller product shape used by the existing customer UI.
 */
function logspanelRequest(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
{
    $base = rtrim((string)(defined('API_BASE_URL') ? API_BASE_URL : ''), '/');
    $key = (string)(defined('RESELLER_API_KEY') ? RESELLER_API_KEY : '');
    if ($base === '' || $key === '') return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Logspanel API configuration is incomplete.'];

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $key,
        'Content-Type: application/json',
        'User-Agent: LogsPlug-Reseller/1.0 (+server-to-server)',
    ];
    if ($idempotencyKey !== null && $idempotencyKey !== '') $headers[] = 'Idempotency-Key: ' . $idempotencyKey;

    $url = $base . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];
    if ($payload !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if ($status >= 200 && $status < 300 && is_array($data)) {
        return ['ok'=>true,'status'=>$status,'data'=>$data,'error'=>''];
    }
    $message = is_array($data) && !empty($data['message']) ? (string)$data['message'] : '';
    $code = is_array($data) && !empty($data['code']) ? (string)$data['code'] : '';
    $error = $code !== '' ? $code . ': ' : '';
    $error .= $message !== '' ? $message : ($curlError !== '' ? 'Network error: ' . $curlError : 'Logspanel API returned HTTP ' . $status . '.');
    error_log('Logspanel API failure: HTTP ' . $status . '; URL=' . $url . '; error=' . $error);
    return ['ok'=>false,'status'=>$status,'data'=>is_array($data)?$data:null,'error'=>$error];
}

function logspanelFetchCatalog(): array
{
    $cacheKey = hash('sha256', (string)API_BASE_URL . '|' . (string)RESELLER_API_KEY);
    $cacheFile = sys_get_temp_dir() . '/logspanel-products-' . $cacheKey . '.json';
    $ttl = 60;

    if (is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['products']) && is_array($cached['products'])) return $cached;
    }

    $products = [];
    $page = 1;
    do {
        $r = logspanelRequest('GET', '/logs/categories?per_page=100&page=' . $page);
        if (!$r['ok']) {
            if (is_file($cacheFile)) {
                $cached = json_decode((string)@file_get_contents($cacheFile), true);
                if (is_array($cached) && !empty($cached['products'])) return $cached;
            }
            return ['products'=>[],'error'=>$r['error']];
        }

        $data = $r['data']['data'] ?? [];
        if (!is_array($data)) return ['products'=>[],'error'=>'Logspanel returned an invalid catalog response.'];

        foreach ($data as $p) {
            if (!is_array($p)) continue;
            $id = isset($p['id']) ? (int)$p['id'] : 0;
            $ref = isset($p['product_ref']) ? trim((string)$p['product_ref']) : '';
            if ($id === 0 && $ref === '') continue;
            $price = isset($p['selling_price']) ? (float)$p['selling_price'] : (float)($p['price'] ?? 0);
            $stock = isset($p['available_quantity']) ? (int)$p['available_quantity'] : 0;
            $products[] = [
                'id' => $id,
                'product_ref' => $ref,
                'name' => (string)($p['name'] ?? 'Unnamed service'),
                'description' => (string)($p['description'] ?? ''),
                'category' => is_array($p['parent_category'] ?? null) ? (string)($p['parent_category']['name'] ?? 'Other') : 'Other',
                'reseller_price' => $price,
                'selling_price' => $price,
                'currency' => (string)($p['currency'] ?? 'NGN'),
                'in_stock' => $stock,
                'available_quantity' => $stock,
                'min_quantity' => max(1, (int)($p['min_quantity'] ?? 1)),
                'max_quantity' => max(1, (int)($p['max_quantity'] ?? 100)),
                'purchasable' => !isset($p['purchasable']) || (bool)$p['purchasable'],
                'image_url' => (string)($p['image'] ?? ''),
            ];
        }

        $next = $r['data']['links']['next'] ?? null;
        if ($next) {
            $parts = parse_url((string)$next);
            $nextPage = isset($parts['query']) ? null : null;
            parse_str((string)($parts['query'] ?? ''), $query);
            $page = isset($query['page']) ? max($page + 1, (int)$query['page']) : $page + 1;
        } else {
            break;
        }
    } while ($page < 1000);

    $result = ['products'=>$products,'error'=>''];
    @file_put_contents($cacheFile, json_encode($result), LOCK_EX);
    return $result;
}

function logspanelPurchase(string $productRef, int $categoryId, int $qty, string $idempotencyKey): array
{
    $payload = [
        'quantity' => $qty,
        'idempotency_key' => $idempotencyKey,
    ];
    if ($productRef !== '') $payload['product_ref'] = $productRef;
    else $payload['category_id'] = $categoryId;

    $r = logspanelRequest('POST', '/logs/orders', $payload, $idempotencyKey);
    if (!$r['ok']) return $r;

    $data = $r['data'];
    $order = is_array($data['data'] ?? null) ? $data['data'] : [];
    return ['ok'=>true,'status'=>$r['status'],'data'=>$data,'order'=>$order,'error'=>''];
}
