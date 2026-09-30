<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';
require_once __DIR__.'/naira.php';
require_once __DIR__.'/logspanel_api.php';

function fetchResellerProductsFast(string $baseUrl='',string $apiKey='',int $ttl=60):array
{
    try{
        $st=db()->prepare('SELECT value FROM settings WHERE key=?');$st->execute(['catalog_logspanel']);$raw=$st->fetchColumn();
        if(is_string($raw)&&$raw!==''){
            $decoded=json_decode($raw,true);
            if(is_array($decoded['data']??null))return ['products'=>logspanelNormalizeCachedProducts($decoded['data']),'error'=>''];
        }
    }catch(Throwable $e){}
    return logspanelFetchLogCatalog();
}
function logspanelNormalizeCachedProducts(array $items):array
{
    $out=[];
    foreach($items as $p){
        $parent=is_array($p['parent_category']??null)?$p['parent_category']:[];
        $price=nairaDecimal((string)($p['selling_price']??$p['price']??'0'));
        $out[]=['id'=>(int)($p['id']??0),'category_id'=>(int)($p['id']??0),'product_ref'=>(string)($p['product_ref']??''),'name'=>(string)($p['name']??'Unnamed service'),
            'description'=>(string)($p['description']??''),'category'=>(string)($parent['name']??'Other'),'selling_price'=>$price,'reseller_price'=>$price,
            'base_price'=>nairaDecimal((string)($p['price']??$price)),'currency'=>(string)($p['currency']??'NGN'),
            'in_stock'=>(int)($p['available_quantity']??0),'available_quantity'=>(int)($p['available_quantity']??0),
            'min_quantity'=>max(1,(int)($p['min_quantity']??1)),'max_quantity'=>min(100,max(1,(int)($p['max_quantity']??100))),
            'purchasable'=>!isset($p['purchasable'])||(bool)$p['purchasable'],'image_url'=>(string)($p['image']??'')];
    }return $out;
}
