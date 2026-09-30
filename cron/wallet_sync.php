<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/db.php';
foreach(ProviderRegistry::ids() as $id){
 try{
  $p=ProviderRegistry::get($id);$balance=$p->walletBalance();
  if($balance!==null)db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')
   ->execute(['provider_wallet_'.$id,$balance]);
 }catch(Throwable $e){error_log('Provider wallet sync '.$id.': '.$e->getMessage());}
}
