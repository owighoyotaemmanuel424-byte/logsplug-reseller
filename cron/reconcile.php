<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
$pdo=db();
$st=$pdo->query("SELECT id,user_id,provider,provider_ref,status FROM orders WHERE status='pending' AND provider_ref IS NOT NULL ORDER BY id LIMIT 100");
foreach($st->fetchAll() as $order){
 try{
  $provider=ProviderRegistry::get((string)$order['provider']);
  $r=$provider->getOrder((string)$order['provider_ref']);
  if(!$r['ok'])continue;
  $data=$r['data']['data']??[];
  $status=strtolower((string)($data['status']??$r['data']['status']??''));
  if($status==='completed'||$status==='complete'||$status==='success'){
   $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND status=?')->execute(['completed',(int)$order['id'],'pending']);
  }elseif(in_array($status,['failed','cancelled','canceled'],true)){
   $pdo->beginTransaction();
   try{
    $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND status=?')->execute(['failed',(int)$order['id'],'pending']);
    $pdo->commit();
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }
 }catch(Throwable $e){error_log('Order reconcile #'.$order['id'].': '.$e->getMessage());}
}
