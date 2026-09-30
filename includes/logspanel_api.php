<?php
declare(strict_types=1);
require_once __DIR__.'/providers/ProviderRegistry.php';
function logspanelProvider():LogspanelProvider{return ProviderRegistry::get('logspanel');}
function logspanelFetchLogCatalog():array{
 try{$items=logspanelProvider()->catalog();$products=[];foreach($items as $p){$parent=is_array($p['parent_category']??null)?$p['parent_category']:[];$price=(string)($p['selling_price']??$p['price']??'0');$products[]=['id'=>(int)($p['id']??0),'category_id'=>(int)($p['id']??0),'product_ref'=>(string)($p['product_ref']??''),'name'=>(string)($p['name']??'Unnamed service'),'description'=>(string)($p['description']??''),'category'=>(string)($parent['name']??'Other'),'selling_price'=>$price,'reseller_price'=>$price,'base_price'=>(string)($p['price']??$price),'currency'=>(string)($p['currency']??'NGN'),'in_stock'=>(int)($p['available_quantity']??0),'available_quantity'=>(int)($p['available_quantity']??0),'min_quantity'=>max(1,(int)($p['min_quantity']??1)),'max_quantity'=>min(100,max(1,(int)($p['max_quantity']??100))),'purchasable'=>!isset($p['purchasable'])||(bool)$p['purchasable'],'image_url'=>(string)($p['image']??'')];}return ['products'=>$products,'error'=>''];}catch(Throwable $e){return ['products'=>[],'error'=>$e->getMessage()];}
}
function logspanelFetchCatalog():array{return logspanelFetchLogCatalog();}
function logspanelFetchNumberCountries():array{return logspanelProvider()->numberCountries();}
function logspanelFetchNumberServices(?int $countryId=null):array{return $countryId===null?['services'=>[],'error'=>'country_id is required.']:logspanelProvider()->numberServices($countryId);}
function logspanelFetchAllNumberServices(array $countries):array{return logspanelProvider()->allNumberServices($countries);}
function logspanelFetchBoostCategories():array{return logspanelProvider()->boostCategories();}
function logspanelFetchBoostServices(?string $category=null):array{return logspanelProvider()->boostServices($category);}
function logspanelFetchAllBoostServices(array $categories=[]):array{return logspanelProvider()->allBoostServices($categories);}
function logspanelPurchase(string $productRef,int $categoryId,int $qty,string $idempotencyKey):array{ $payload=['quantity'=>$qty]; if(trim($productRef)!=='')$payload['product_ref']=trim($productRef); else $payload['category_id']=$categoryId; return logspanelProvider()->createOrder($payload,$idempotencyKey); }
