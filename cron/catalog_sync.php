<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/db.php';
$p=ProviderRegistry::get('logspanel');
$items=$p->catalog();
$pdo=db();
$save=function(string $key,array $data)use($pdo):void{$pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')->execute([$key,json_encode(['data'=>$data,'synced_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES)]);};
$save('catalog_logspanel',$items);
$countries=$p->numberCountries();if($countries['error']===''){$save('catalog_numbers_countries',$countries['countries']);$ns=$p->allNumberServices($countries['countries']);if($ns['error']==='')$save('catalog_numbers_services',$ns['services']);}
$bc=$p->boostCategories();if($bc['error']===''){$save('catalog_boost_categories',$bc['categories']);$bs=$p->allBoostServices($bc['categories']);if($bs['error']==='')$save('catalog_boost_services',$bs['services']);}
