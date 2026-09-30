<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/db.php';
foreach(ProviderRegistry::ids() as $id){
 try{
  $p=ProviderRegistry::get($id);$h=$p->health();
  db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')
   ->execute(['provider_health_'.$id,json_encode($h,JSON_UNESCAPED_SLASHES)]);
 }catch(Throwable $e){error_log('Provider health '.$id.': '.$e->getMessage());}
}
