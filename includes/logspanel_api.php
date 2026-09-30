<?php
/**
 * Logspanel API v1 adapter.
 * API keys remain server-side.
 */

function logspanelRequest(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
{
    $base = rtrim((string)(defined('API_BASE_URL') ? API_BASE_URL : ''), '/');
    $key = (string)(defined('RESELLER_API_KEY') ? RESELLER_API_KEY : '');
    if ($base === '' || $key === '') return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Logspanel API configuration is incomplete.'];

    $headers = ['Accept: application/json','Authorization: Bearer '.$key,'Content-Type: application/json','User-Agent: LogsPlug-Reseller/1.0'];
    if ($idempotencyKey !== null && $idempotencyKey !== '') $headers[] = 'Idempotency-Key: '.$idempotencyKey;

    $url = (preg_match('#^https?://#i', $path) ? $path : $base.'/'.ltrim($path,'/'));
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_CONNECTTIMEOUT=>3,
        CURLOPT_TIMEOUT=>20, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_ENCODING=>'',
        CURLOPT_CUSTOMREQUEST=>strtoupper($method)
    ]);
    if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $curlError=curl_error($ch); curl_close($ch);
    $data=is_string($raw)&&$raw!==''?json_decode($raw,true):null;
    if ($status>=200&&$status<300&&is_array($data)) return ['ok'=>true,'status'=>$status,'data'=>$data,'error'=>''];
    $message=is_array($data)?(string)($data['message']??''):''; $code=is_array($data)?(string)($data['code']??''):'';
    $error=($code!==''?$code.': ':'').($message!==''?$message:($curlError!==''?'Network error: '.$curlError:'Logspanel API returned HTTP '.$status.'.'));
    error_log('Logspanel API failure: HTTP '.$status.'; error='.$error);
    return ['ok'=>false,'status'=>$status,'data'=>is_array($data)?$data:null,'error'=>$error];
}

/** Follow the provider's links.next exactly until it is null. */
function logspanelFetchAllPages(string $path, int $perPage=100): array
{
    $items=[]; $next=$path;
    $first=true; $guard=0; $seen=[];
    while ($next!==null && $guard++<10000) {
        if ($first && !preg_match('/[?&]per_page=/',$next)) {
            $next .= (strpos($next,'?')===false?'?':'&').'per_page='.max(1,min(100,$perPage)).'&page=1';
        }
        $first=false;
        if (isset($seen[$next])) return ['items'=>$items,'error'=>'Logspanel returned a cyclic pagination link.'];
        $seen[$next]=true;
        $r=logspanelRequest('GET',$next);
        if (!$r['ok']) return ['items'=>$items,'error'=>$r['error']];
        $data=$r['data']['data']??[];
        if (!is_array($data)) return ['items'=>$items,'error'=>'Invalid Logspanel catalog response.'];
        foreach($data as $item) if(is_array($item)) $items[]=$item;
        $next=$r['data']['links']['next']??null;
    }
    return ['items'=>$items,'error'=>$next!==null?'Pagination safety limit reached.':''];
}

function logspanelFetchParentCategories(): array {
    $r=logspanelRequest('GET','/logs/parent-categories');
    return ['categories'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
}

function logspanelNormalizeLogProducts(array $items): array
{
    $out=[];
    foreach($items as $p){
        $id=isset($p['id'])?(int)$p['id']:0; $ref=trim((string)($p['product_ref']??''));
        if($id===0&&$ref==='') continue;
        $parent=is_array($p['parent_category']??null)?$p['parent_category']:[];
        $price=(float)($p['selling_price']??$p['price']??0); $stock=(int)($p['available_quantity']??0);
        $out[]=[
            'id'=>$id,'product_ref'=>$ref,'name'=>(string)($p['name']??'Unnamed service'),
            'description'=>(string)($p['description']??''),'category'=>(string)($parent['name']??'Other'),
            'parent_category_id'=>isset($parent['id'])?(int)$parent['id']:null,
            'reseller_price'=>$price,'selling_price'=>$price,'base_price'=>(float)($p['price']??$price),
            'currency'=>(string)($p['currency']??'NGN'),'in_stock'=>$stock,'available_quantity'=>$stock,
            'min_quantity'=>max(1,(int)($p['min_quantity']??1)),'max_quantity'=>max(1,(int)($p['max_quantity']??100)),
            'purchasable'=>!isset($p['purchasable'])||(bool)$p['purchasable'],'image_url'=>(string)($p['image']??'')
        ];
    }
    return $out;
}

/**
 * Complete log catalog. We read BOTH documented representations and merge them.
 * This prevents products exposed only by the reference representation from being lost.
 */
function logspanelFetchLogCatalog(): array
{
    // /logs/categories is the provider's documented complete catalog endpoint.
    // Do not also call /logs/products here: doing both doubles requests and can
    // trip the provider's rate limiter without adding catalog coverage.
    $base=(string)(defined('API_BASE_URL')?API_BASE_URL:'');
    $cacheKey=hash('sha256',$base.'|log-categories');
    $cacheFile=sys_get_temp_dir().'/logspanel-log-catalog-'.$cacheKey.'.json';
    $ttl=300;

    if(is_file($cacheFile)&&(time()-(int)@filemtime($cacheFile))<$ttl){
        $cached=json_decode((string)@file_get_contents($cacheFile),true);
        if(is_array($cached)&&isset($cached['products'])) return $cached;
    }

    $categoryResult=logspanelFetchAllPages('/logs/categories',100);
    if($categoryResult['error']!==''){
        $result=['products'=>[],'error'=>'Categories: '.$categoryResult['error']];
        // Keep a usable stale catalog during a temporary provider rate limit.
        if(is_file($cacheFile)){
            $cached=json_decode((string)@file_get_contents($cacheFile),true);
            if(is_array($cached)&&isset($cached['products'])&&!empty($cached['products'])){
                $cached['error']=$result['error'].' | Showing cached catalog.';
                return $cached;
            }
        }
        return $result;
    }

    $products=logspanelNormalizeLogProducts($categoryResult['items']);
    $result=['products'=>$products,'error'=>''];
    @file_put_contents($cacheFile,json_encode($result,JSON_UNESCAPED_SLASHES),LOCK_EX);
    return $result;
}

function logspanelFetchCatalog(): array { return logspanelFetchLogCatalog(); }

function logspanelFetchNumberCountries(): array {
    $r=logspanelRequest('GET','/numbers/countries');
    return ['countries'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
}

/** The provider currently validates country_id even though the docs describe it as optional. */
function logspanelFetchNumberServices(?int $countryId=null): array {
    if($countryId===null) return ['services'=>[],'error'=>'country_id is required by the number-services endpoint.'];
    $r=logspanelRequest('GET','/numbers/services?country_id='.rawurlencode((string)$countryId));
    return ['services'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
}

function logspanelFetchAllNumberServices(array $countries): array {
    $all=[]; $errors=[];
    foreach($countries as $country){
        if(!is_array($country)||!isset($country['id'])) continue;
        $id=(int)$country['id'];
        $r=logspanelFetchNumberServices($id);
        if($r['error']!==''){ $errors[]='Country '.$id.': '.$r['error']; continue; }
        foreach($r['services'] as $s){
            if(!is_array($s)) continue;
            if(!isset($s['country_id'])) $s['country_id']=$id;
            if(!isset($s['country_name'])) $s['country_name']=(string)($country['name']??'');
            $key=(string)($s['country_id']??$id).':'.(string)($s['service_id']??$s['service_name']??'');
            $all[$key]=$s;
        }
    }
    return ['services'=>array_values($all),'error'=>implode(' | ',array_unique($errors))];
}

function logspanelFetchNumberAreaCodes(int $countryId,string $serviceId): array {
    $r=logspanelRequest('GET','/numbers/area-codes?country_id='.$countryId.'&service_id='.rawurlencode($serviceId));
    $data=$r['data']['data']??[];
    return ['codes'=>is_array($data['codes']??null)?$data['codes']:[],'error'=>$r['ok']?'':$r['error']];
}

function logspanelFetchBoostCategories(): array {
    $r=logspanelRequest('GET','/boost/categories');
    return ['categories'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
}

function logspanelFetchBoostServices(?string $category=null): array {
    $path='/boost/services'.($category!==null&&trim($category)!==''?'?category='.rawurlencode($category):'');
    $r=logspanelRequest('GET',$path);
    return ['services'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
}

function logspanelFetchAllBoostServices(array $categories=[]): array {
    $all=[];$errors=[];
    $r=logspanelFetchBoostServices();
    if($r['error']==='') foreach($r['services'] as $s) $all[]=$s; else $errors[]=$r['error'];
    if(!$all&&$categories) foreach($categories as $c){
        $name=is_array($c)?(string)($c['name']??''):(string)$c; if($name==='') continue;
        $r=logspanelFetchBoostServices($name);
        if($r['error']!==''){ $errors[]=$r['error']; continue; }
        foreach($r['services'] as $s) $all[]=$s;
    }
    $unique=[];
    foreach($all as $s){$id=(string)($s['service_id']??'');if($id!=='')$unique[$id]=$s;}
    return ['services'=>array_values($unique),'error'=>implode(' | ',array_unique($errors))];
}

function logspanelPurchase(string $productRef,int $categoryId,int $qty,string $idempotencyKey): array {
    $payload=['quantity'=>$qty,'idempotency_key'=>$idempotencyKey];
    if($productRef!=='')$payload['product_ref']=$productRef;else$payload['category_id']=$categoryId;
    $r=logspanelRequest('POST','/logs/orders',$payload,$idempotencyKey);
    if(!$r['ok'])return $r;
    $data=$r['data']; return ['ok'=>true,'status'=>$r['status'],'data'=>$data,'order'=>is_array($data['data']??null)?$data['data']:[],'error'=>''];
}
