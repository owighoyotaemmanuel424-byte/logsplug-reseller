<?php
/**
 * Logspanel API v1 adapter.
 * API keys stay server-side.
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
        CURLOPT_TIMEOUT => 20,
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

/**
 * Fetch a paginated Logspanel endpoint completely.
 * The API's links.next is authoritative; it is followed until null.
 */
function logspanelFetchAllPages(string $path, int $perPage = 100): array
{
    $items = [];
    $page = 1;
    $seen = [];

    do {
        $separator = strpos($path, '?') === false ? '?' : '&';
        $requestPath = $path . $separator . 'per_page=' . max(1, min(100, $perPage)) . '&page=' . $page;
        $r = logspanelRequest('GET', $requestPath);

        if (!$r['ok']) return ['items'=>$items, 'error'=>$r['error']];
        $body = $r['data'];
        $data = $body['data'] ?? [];

        if (!is_array($data)) return ['items'=>$items, 'error'=>'Logspanel returned an invalid catalog response.'];
        foreach ($data as $item) if (is_array($item)) $items[] = $item;

        $next = $body['links']['next'] ?? null;
        if (!$next) break;

        $parts = parse_url((string)$next);
        parse_str((string)($parts['query'] ?? ''), $query);
        $nextPage = isset($query['page']) ? (int)$query['page'] : ($page + 1);

        if ($nextPage < 1 || isset($seen[$nextPage])) {
            return ['items'=>$items, 'error'=>'Logspanel returned an invalid pagination link.'];
        }
        $seen[$page] = true;
        $page = $nextPage;
    } while ($page < 10000);

    return ['items'=>$items, 'error'=>''];
}

function logspanelFetchParentCategories(): array
{
    $r = logspanelRequest('GET', '/logs/parent-categories');
    if (!$r['ok']) return ['categories'=>[],'error'=>$r['error']];
    return ['categories'=>is_array($r['data']['data'] ?? null) ? $r['data']['data'] : [],'error'=>''];
}

function logspanelFetchLogCatalog(): array
{
    $cacheKey = hash('sha256', (string)API_BASE_URL . '|' . (string)RESELLER_API_KEY);
    $cacheFile = sys_get_temp_dir() . '/logspanel-log-catalog-' . $cacheKey . '.json';
    $ttl = 60;

    if (is_file($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string)@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['products'])) return $cached;
    }

    // /logs/categories is the documented complete category catalog.
    $result = logspanelFetchAllPages('/logs/categories', 100);
    if ($result['error'] !== '') {
        if (is_file($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['products'])) return $cached;
        }
        return ['products'=>[],'error'=>$result['error']];
    }

    $products = [];
    foreach ($result['items'] as $p) {
        $id = isset($p['id']) ? (int)$p['id'] : 0;
        $ref = isset($p['product_ref']) ? trim((string)$p['product_ref']) : '';
        if ($id === 0 && $ref === '') continue;

        $price = isset($p['selling_price']) ? (float)$p['selling_price'] : (float)($p['price'] ?? 0);
        $stock = isset($p['available_quantity']) ? (int)$p['available_quantity'] : 0;
        $parent = is_array($p['parent_category'] ?? null) ? $p['parent_category'] : [];

        $products[] = [
            'id'=>$id,
            'product_ref'=>$ref,
            'name'=>(string)($p['name'] ?? 'Unnamed service'),
            'description'=>(string)($p['description'] ?? ''),
            'category'=>(string)($parent['name'] ?? 'Other'),
            'parent_category_id'=>isset($parent['id']) ? (int)$parent['id'] : null,
            'reseller_price'=>$price,
            'selling_price'=>$price,
            'base_price'=>isset($p['price']) ? (float)$p['price'] : $price,
            'currency'=>(string)($p['currency'] ?? 'NGN'),
            'in_stock'=>$stock,
            'available_quantity'=>$stock,
            'min_quantity'=>max(1,(int)($p['min_quantity'] ?? 1)),
            'max_quantity'=>max(1,(int)($p['max_quantity'] ?? 100)),
            'purchasable'=>!isset($p['purchasable']) || (bool)$p['purchasable'],
            'image_url'=>(string)($p['image'] ?? ''),
        ];
    }

    $result = ['products'=>$products,'error'=>''];
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $result;
}

function logspanelFetchCatalog(): array
{
    return logspanelFetchLogCatalog();
}

function logspanelFetchNumberCountries(): array
{
    $r = logspanelRequest('GET', '/numbers/countries');
    return ['countries'=>is_array($r['data']['data'] ?? null) ? $r['data']['data'] : [], 'error'=>$r['ok'] ? '' : $r['error']];
}

function logspanelFetchNumberServices(?int $countryId = null): array
{
    $path = '/numbers/services';
    if ($countryId !== null) $path .= '?country_id=' . $countryId;
    $r = logspanelRequest('GET', $path);
    return ['services'=>is_array($r['data']['data'] ?? null) ? $r['data']['data'] : [], 'error'=>$r['ok'] ? '' : $r['error']];
}

function logspanelFetchNumberAreaCodes(int $countryId, string $serviceId): array
{
    $path = '/numbers/area-codes?country_id=' . $countryId . '&service_id=' . rawurlencode($serviceId);
    $r = logspanelRequest('GET', $path);
    $data = $r['data']['data'] ?? [];
    return ['codes'=>is_array($data['codes'] ?? null) ? $data['codes'] : [], 'error'=>$r['ok'] ? '' : $r['error']];
}

function logspanelFetchBoostCategories(): array
{
    $r = logspanelRequest('GET', '/boost/categories');
    return ['categories'=>is_array($r['data']['data'] ?? null) ? $r['data']['data'] : [], 'error'=>$r['ok'] ? '' : $r['error']];
}

function logspanelFetchBoostServices(?string $category = null): array
{
    $path = '/boost/services';
    if ($category !== null && trim($category) !== '') $path .= '?category=' . rawurlencode($category);
    $r = logspanelRequest('GET', $path);
    return ['services'=>is_array($r['data']['data'] ?? null) ? $r['data']['data'] : [], 'error'=>$r['ok'] ? '' : $r['error']];
}

function logspanelFetchAllBoostServices(array $categories = []): array
{
    $all = [];
    $errors = [];

    // Fetch unfiltered services first; the API documents category as optional.
    $result = logspanelFetchBoostServices();
    if ($result['error'] === '') {
        $all = $result['services'];
    } else {
        $errors[] = $result['error'];
    }

    // If the unfiltered endpoint is restricted by the provider, fall back to each category.
    if (!$all && $categories) {
        foreach ($categories as $category) {
            $name = is_array($category) ? (string)($category['name'] ?? '') : (string)$category;
            if ($name === '') continue;
            $result = logspanelFetchBoostServices($name);
            if ($result['error'] !== '') {
                $errors[] = $result['error'];
                continue;
            }
            foreach ($result['services'] as $service) $all[] = $service;
        }
    }

    $unique = [];
    foreach ($all as $service) {
        $id = (string)($service['service_id'] ?? '');
        if ($id === '') continue;
        $unique[$id] = $service;
    }

    return ['services'=>array_values($unique),'error'=>implode(' | ', array_unique($errors))];
}

function logspanelPurchase(string $productRef, int $categoryId, int $qty, string $idempotencyKey): array
{
    $payload = ['quantity'=>$qty,'idempotency_key'=>$idempotencyKey];
    if ($productRef !== '') $payload['product_ref'] = $productRef;
    else $payload['category_id'] = $categoryId;

    $r = logspanelRequest('POST', '/logs/orders', $payload, $idempotencyKey);
    if (!$r['ok']) return $r;

    $data = $r['data'];
    $order = is_array($data['data'] ?? null) ? $data['data'] : [];
    return ['ok'=>true,'status'=>$r['status'],'data'=>$data,'order'=>$order,'error'=>''];
}
