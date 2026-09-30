<?php
declare(strict_types=1);
require_once __DIR__.'/providers/ProviderRegistry.php';
require_once __DIR__.'/naira.php';

function logspanelProvider(): LogspanelProvider { return ProviderRegistry::get('logspanel'); }

function logspanelFetchLogCatalog(): array {
    try {
        $items=logspanelProvider()->catalog();$products=[];
        foreach($items as $p){
            $parent=is_array($p['parent_category']??null)?$p['parent_category']:[];
            $price=nairaDecimal((string)($p['selling_price']??$p['price']??'0'));
            $products[]=[
                'id'=>(int)($p['id']??0),'product_ref'=>(string)($p['product_ref']??''),
                'name'=>(string)($p['name']??'Unnamed service'),'description'=>(string)($p['description']??''),
                'category'=>(string)($parent['name']??'Other'),'parent_category_id'=>isset($parent['id'])?(int)$parent['id']:null,
                'reseller_price'=>$price,'selling_price'=>$price,'base_price'=>nairaDecimal((string)($p['price']??$price)),
                'currency'=>(string)($p['currency']??'NGN'),'in_stock'=>(int)($p['available_quantity']??0),
                'available_quantity'=>(int)($p['available_quantity']??0),'min_quantity'=>max(1,(int)($p['min_quantity']??1)),
                'max_quantity'=>max(1,(int)($p['max_quantity']??100)),'purchasable'=>!isset($p['purchasable'])||(bool)$p['purchasable'],
                'image_url'=>(string)($p['image']??'')
            ];
        }
        return ['products'=>$products,'error'=>''];
    }catch(Throwable $e){return ['products'=>[],'error'=>$e->getMessage()];}
}
function logspanelFetchCatalog():array{return logspanelFetchLogCatalog();}
function logspanelPurchase(string $productRef,int $categoryId,int $qty,string $idempotencyKey):array{
    return logspanelProvider()->createOrder(['product_ref'=>$productRef,'category_id'=>$categoryId,'quantity'=>$qty],$idempotencyKey);
}
