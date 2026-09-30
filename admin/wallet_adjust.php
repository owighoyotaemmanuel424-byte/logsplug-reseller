<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/includes/csrf.php';
require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed.');}
$userId=(int)($_POST['user_id']??0);$amount=trim((string)($_POST['amount']??''));$direction=(string)($_POST['direction']??'credit');
if($userId<1||!preg_match('/^\d+(?:\.\d{1,2})?$/',$amount)){http_response_code(422);exit('Invalid wallet adjustment.');}
if($direction==='credit'){
 if(!adminCreditWallet($userId,$amount)){http_response_code(409);exit('Wallet adjustment failed.');}
}else{
 $pdo=getDb();$pdo->beginTransaction();
 try{
  $st=$pdo->prepare('SELECT wallet_balance FROM users WHERE id=? FOR UPDATE');$st->execute([$userId]);$balance=$st->fetchColumn();
  if($balance===false||nairaKobo((string)$balance)<nairaKobo($amount))throw new RuntimeException('Insufficient balance.');
  $ref='admin-debit-'.bin2hex(random_bytes(12));
  $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,description) VALUES(?,?,?,?,?)')->execute([$userId,'debit',nairaKobo($amount),$ref,'Administrative wallet debit']);
  $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?')->execute([$amount,$userId]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(409);exit('Wallet adjustment failed.');}
}
header('Location: users.php?adjusted=1');exit;
