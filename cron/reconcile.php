<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/naira.php';
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
    $st=$pdo->prepare('SELECT id,user_id,total_amount,provider,provider_ref FROM orders WHERE id=? FOR UPDATE');$st->execute([(int)$order['id']]);$fresh=$st->fetch();
    if($fresh && $fresh['status']==='pending'){
      $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND status=?')->execute(['failed',(int)$order['id'],'pending']);
      $refundRef='refund-reconcile-'.(string)$fresh['provider_ref'];
      $exists=$pdo->prepare('SELECT id FROM wallet_transactions WHERE provider=? AND provider_ref=? LIMIT 1');$exists->execute([(string)$fresh['provider'],$refundRef]);
      if(!$exists->fetchColumn()){
        $amount=(string)$fresh['total_amount'];
        $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,provider,provider_ref,description) VALUES(?,?,?,?,?,?,?)')
          ->execute([(int)$fresh['user_id'],'refund',nairaKobo($amount),$refundRef,(string)$fresh['provider'],$refundRef,'Refund for failed provider order #'.(int)$fresh['id']]);
        $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?')->execute([$amount,(int)$fresh['user_id']]);
      }
    }
    $pdo->commit();
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }
 }catch(Throwable $e){error_log('Order reconcile #'.$order['id'].': '.$e->getMessage());}
}
