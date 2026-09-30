<?php
declare(strict_types=1);
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/naira.php';
require_once __DIR__.'/includes/wallet.php';

if(session_status()===PHP_SESSION_NONE){
    session_name('logsplug_session');
    session_set_cookie_params([
        'lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax'
    ]);
    ini_set('session.use_strict_mode','1');
    session_start();
}

function getDb(): PDO { return db(); }

function currentUserId(): int {
    $id=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;
    if($id<1) throw new RuntimeException('Authentication required.');
    return $id;
}

function getCurrentUser(): ?array {
    $id=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;
    if($id<1)return null;
    $st=db()->prepare('SELECT id,email,name,created_at,wallet_balance FROM users WHERE id=? LIMIT 1');
    $st->execute([$id]);$row=$st->fetch();
    return $row?:null;
}

function requireLogin():void {
    if(getCurrentUser()===null){header('Location: login.php?redirect='.rawurlencode($_SERVER['REQUEST_URI']??'index.php'));exit;}
}

function registerUser(string $email,string $password,string $name):?string {
    $email=strtolower(trim($email));$name=trim($name);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))return 'Enter a valid email address.';
    if(strlen($password)<10)return 'Password must be at least 10 characters.';
    if(!defined('PASSWORD_ARGON2ID'))return 'Argon2id is required by this deployment.';
    $hash=password_hash($password,PASSWORD_ARGON2ID);
    if($hash===false)return 'Unable to secure password.';
    $pdo=db();
    try{
        $pdo->beginTransaction();
        $st=$pdo->prepare('INSERT INTO users(email,password_hash,name,wallet_balance) VALUES(?,?,?,0) RETURNING id,email,name,created_at,wallet_balance');
        $st->execute([$email,$hash,$name]);$user=$st->fetch();
        $pdo->prepare('INSERT INTO wallets(user_id,balance) VALUES(?,0) ON CONFLICT(user_id) DO NOTHING')->execute([(int)$user['id']]);
        $pdo->commit();
        session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];
        return null;
    }catch(PDOException $e){
        if($pdo->inTransaction())$pdo->rollBack();
        if((string)$e->getCode()==='23505')return 'An account with that email already exists.';
        error_log('Registration failed: '.$e->getMessage());return 'Registration could not be completed.';
    }
}

function loginUser(string $email,string $password):bool {
    $st=db()->prepare('SELECT id,password_hash FROM users WHERE LOWER(email)=LOWER(?) LIMIT 1');
    $st->execute([trim($email)]);$row=$st->fetch();
    if(!$row||!password_verify($password,(string)$row['password_hash']))return false;
    if(password_needs_rehash((string)$row['password_hash'],PASSWORD_ARGON2ID)){
        $hash=password_hash($password,PASSWORD_ARGON2ID);
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash,(int)$row['id']]);
    }
    session_regenerate_id(true);$_SESSION['user_id']=(int)$row['id'];return true;
}

function logoutUser():void {
    $_SESSION=[];if(ini_get('session.use_cookies')){ $p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],'',$p['secure'],$p['httponly']); }session_destroy();
}

function getWalletBalance(int $userId):string {
    $st=db()->prepare('SELECT wallet_balance FROM users WHERE id=?');$st->execute([$userId]);$v=$st->fetchColumn();return $v===false?'0.00':nairaDecimal((string)$v);
}

function getWalletTransactions(int $userId):array {
    $st=db()->prepare('SELECT type,amount_kobo,reference,description,created_at FROM wallet_transactions WHERE user_id=? ORDER BY created_at DESC,id DESC');
    $st->execute([$userId]);$out=[];
    foreach($st->fetchAll() as $r)$out[]=['type'=>$r['type'],'amount'=>nairaDecimal((string)$r['amount_kobo']),'reference'=>$r['reference'],'description'=>$r['description'],'date'=>$r['created_at']];
    return $out;
}

function getOrderById(int $orderId):?array {
    $uid=currentUserId();
    $st=db()->prepare('SELECT * FROM orders WHERE id=? AND user_id=? LIMIT 1');
    $st->execute([$orderId,$uid]);$row=$st->fetch();
    return $row?:null;
}

function getOrdersForUser(int $userId,int $page=1,int $perPage=20):array {
    $page=max(1,$page);$offset=($page-1)*$perPage;
    $count=db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');$count->execute([$userId]);$total=(int)$count->fetchColumn();
    $st=db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT ? OFFSET ?');
    $st->bindValue(1,$userId,PDO::PARAM_INT);$st->bindValue(2,$perPage,PDO::PARAM_INT);$st->bindValue(3,$offset,PDO::PARAM_INT);$st->execute();
    return ['items'=>$st->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>max(1,(int)ceil($total/$perPage))];
}


function getWalletTransactionsPaginated(int $userId,int $page=1,int $perPage=20):array {
 $all=getWalletTransactions($userId);$total=count($all);$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($page,$pages));
 return ['items'=>array_slice($all,($page-1)*$perPage,$perPage),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$pages];
}

function createFundRequest(int $userId,string|int $amount):?string {
 $amount=nairaDecimal((string)$amount);if(nairaKobo($amount)<100)return null;
 $ref='fund-'.bin2hex(random_bytes(12));$pdo=db();
 try{$pdo->prepare('INSERT INTO fund_requests(user_id,amount,reference) VALUES(?,?,?)')->execute([$userId,$amount,$ref]);return $ref;}
 catch(Throwable $e){error_log('Fund request failed: '.$e->getMessage());return null;}
}

function completeFundRequestByReference(string $reference,string $amount):bool {
 $pdo=db();$pdo->beginTransaction();
 try{
  $st=$pdo->prepare('SELECT id,user_id,amount,status FROM fund_requests WHERE reference=? FOR UPDATE');$st->execute([$reference]);$row=$st->fetch();
  if(!$row){$pdo->rollBack();return false;}
  if($row['status']==='completed'){$pdo->commit();return false;}
  $expected=nairaKobo((string)$row['amount']);$received=nairaKobo((string)$amount);
  if($expected!==$received){$pdo->rollBack();return false;}
  $tx='fund-'.$reference;
  $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,provider,provider_ref,description) VALUES(?,?,?,?,?,?,?)')
   ->execute([(int)$row['user_id'],'credit',$expected,$tx,'sprintpay',$reference,'Wallet funding']);
  $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?')->execute([$row['amount'],(int)$row['user_id']]);
  $pdo->prepare("UPDATE fund_requests SET status='completed',completed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$row['id']]);
  $pdo->commit();return true;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Fund completion failed: '.$e->getMessage());return false;}
}

function recordOrder(?int $userId,int $productId,string $productName,int $qty,string $unitPrice,string $apiOrderId='',string $productDetails=''):void {
 if($userId===null)throw new RuntimeException('Customer identity is required.');
 $pdo=db();$total=nairaMultiply($unitPrice,$qty);
 $pdo->prepare('INSERT INTO orders(user_id,product_id,product_name,qty,unit_price,total_amount,api_order_id,status,product_details) VALUES(?,?,?,?,?,?,?,\'completed\',?)')
  ->execute([$userId,$productId,$productName,$qty,nairaDecimal($unitPrice),$total,$apiOrderId,$productDetails]);
}

function reportOrder(int $orderId,int $userId,string $reason):?string {
 $st=db()->prepare('UPDATE orders SET reported_at=CURRENT_TIMESTAMP,report_reason=? WHERE id=? AND user_id=? AND reported_at IS NULL AND created_at>=CURRENT_TIMESTAMP-INTERVAL \'2 hours\'');
 $st->execute([substr(trim($reason),0,500),$orderId,$userId]);return $st->rowCount()===1?null:'Unable to report this order.';
}

function deductWalletBalance(int $userId,string $amount):bool {
 $amount=nairaDecimal($amount);$pdo=db();$pdo->beginTransaction();
 try{
  $st=$pdo->prepare('SELECT wallet_balance FROM users WHERE id=? FOR UPDATE');$st->execute([$userId]);$balance=$st->fetchColumn();
  if($balance===false||nairaKobo((string)$balance)<nairaKobo($amount)){$pdo->rollBack();return false;}
  $ref='legacy-debit-'.bin2hex(random_bytes(8));
  $pdo->prepare('INSERT INTO wallet_transactions(user_id,type,amount_kobo,reference,description) VALUES(?,?,?,?,?)')->execute([$userId,'debit',nairaKobo($amount),$ref,'Wallet debit']);
  $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?')->execute([$amount,$userId]);
  $pdo->commit();return true;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();return false;}
}

function getSetting(string $key):?string {
 $st=db()->prepare('SELECT value FROM settings WHERE key=? LIMIT 1');$st->execute([$key]);$v=$st->fetchColumn();
 return $v===false?null:(string)$v;
}
function setSetting(string $key,string $value):bool {
 db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')->execute([$key,$value]);return true;
}
