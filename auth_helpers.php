<?php
/**
 * Authentication, wallet and order helpers backed by Neon PostgreSQL.
 */
if (!defined('RESELLER_API_KEY')) require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function getDb(): ?PDO
{
    return function_exists('createDatabaseConnection') ? createDatabaseConnection() : null;
}

function authSecret(): string
{
    if (defined('AUTH_SESSION_SECRET') && AUTH_SESSION_SECRET !== '') return AUTH_SESSION_SECRET;
    return hash('sha256', (string) (defined('DATABASE_URL') ? DATABASE_URL : 'logsplug-auth'));
}
function b64e(string $v): string { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); }
function b64d(string $v): string|false {
    $r = strlen($v) % 4;
    if ($r) $v .= str_repeat('=', 4 - $r);
    return base64_decode(strtr($v, '-_', '+/'), true);
}
function setAuthCookie(int $userId): void {
    $encoded = b64e($userId . ':' . (time() + 604800));
    $sig = hash_hmac('sha256', $encoded, authSecret());
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    setcookie('logsplug_auth', $encoded . '.' . $sig, [
        'expires' => time() + 604800, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
}
function clearAuthCookie(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    setcookie('logsplug_auth', '', [
        'expires' => time() - 3600, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax'
    ]);
}
function cookieUserId(): ?int {
    $raw = (string)($_COOKIE['logsplug_auth'] ?? '');
    if ($raw === '' || !str_contains($raw, '.')) return null;
    [$encoded, $sig] = explode('.', $raw, 2);
    if (!hash_equals(hash_hmac('sha256', $encoded, authSecret()), $sig)) return null;
    $decoded = b64d($encoded);
    if ($decoded === false || !str_contains($decoded, ':')) return null;
    [$id, $expires] = explode(':', $decoded, 2);
    if (!ctype_digit($id) || !ctype_digit($expires) || (int)$expires < time()) return null;
    return (int)$id;
}

function appOrigin(): string {
    if (defined('APP_URL') && trim((string) APP_URL) !== '') return rtrim((string) APP_URL, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $host !== '' ? $scheme . '://' . $host : '';
}

function neonAuthCookieHeader(): string {
    $pairs = [];
    foreach ($_COOKIE as $name => $value) {
        if (str_contains((string)$name, 'session_token')) $pairs[] = $name . '=' . $value;
    }
    return implode('; ', $pairs);
}
function neonAuthRequest(string $path, ?array $body = null): array {
    $base = defined('NEON_AUTH_URL') ? rtrim((string)NEON_AUTH_URL, '/') : '';
    if ($base === '') return ['ok'=>false,'data'=>null,'error'=>'NEON_AUTH_URL is not configured.'];
    $origin = appOrigin();
    $headers = ['Accept: application/json','Content-Type: application/json'];
    if ($origin !== '') {
        $headers[] = 'Origin: ' . $origin;
        $headers[] = 'Referer: ' . $origin . '/';
    }
    $cookie = neonAuthCookieHeader();
    if ($cookie !== '') $headers[] = 'Cookie: '.$cookie;
    $opts = ['http'=>['method'=>$body===null?'GET':'POST','header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>15]];
    if ($body !== null) $opts['http']['content']=json_encode($body,JSON_UNESCAPED_SLASHES);
    $ctx=stream_context_create($opts);
    $raw=@file_get_contents($base.'/'.ltrim($path,'/'),false,$ctx);
    $headersOut=$http_response_header ?? [];
    $status=0;
    foreach($headersOut as $h){if(preg_match('/^HTTP\/\S+\s+(\d+)/',$h,$m)){ $status=(int)$m[1]; break; }}
    foreach($headersOut as $h){
        if(stripos($h,'Set-Cookie:')!==0) continue;
        $part=trim(substr($h,11)); $first=explode(';',$part,2)[0];
        if(!str_contains($first,'=')) continue;
        [$n,$v]=explode('=',$first,2);
        setcookie(trim($n),trim($v),['expires'=>time()+604800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
        $_COOKIE[trim($n)]=trim($v);
    }
    $data=is_string($raw)&&$raw!==''?json_decode($raw,true):null;
    return ['ok'=>$status>=200&&$status<300,'data'=>$data,'error'=>is_array($data)?($data['message']??'Authentication failed.'):'Authentication failed.'];
}
function localUserFromNeon(array $user): ?array {
    $pdo=getDb();
    if(!$pdo||empty($user['email'])) return null;

    $email=strtolower(trim((string)$user['email']));
    $name=trim((string)($user['name']??$email));

    try {
        // Neon Auth is authoritative for identity. public.users is the
        // application's local projection keyed by normalized email.
        $st=$pdo->prepare('SELECT id,email,name,created_at FROM public.users WHERE LOWER(BTRIM(email))=LOWER(BTRIM(?)) LIMIT 1');
        $st->execute([$email]);
        $row=$st->fetch();

        if(!$row) {
            $st=$pdo->prepare('INSERT INTO public.users(email,password_hash,name)
                VALUES(?,?,?)
                ON CONFLICT(email) DO UPDATE SET name=EXCLUDED.name
                RETURNING id,email,name,created_at');
            $st->execute([
                $email,
                password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),
                $name
            ]);
            $row=$st->fetch();
        } else {
            $st=$pdo->prepare('UPDATE public.users SET name=? WHERE id=?');
            $st->execute([$name,(int)$row['id']]);
            $row['name']=$name;
        }

        $id=(int)$row['id'];
        $pdo->prepare('INSERT INTO wallets(user_id,balance) VALUES(?,0) ON CONFLICT(user_id) DO NOTHING')->execute([$id]);

        return [
            'id'=>$id,
            'email'=>$row['email'],
            'name'=>$row['name'],
            'created_at'=>$row['created_at']
        ];
    } catch(Throwable $e) {
        error_log('Neon user mapping failed: '.$e->getMessage());
        return null;
    }
}
function getCurrentUser(): ?array {
    // Prefer the durable signed application session. PHP's local session
    // storage on Render is ephemeral, so the signed cookie is the source of
    // truth for this app request.
    $localId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
        ? (int)$_SESSION['user_id']
        : cookieUserId();

    if ($localId !== null && $localId > 0) {
        $pdo = getDb();
        if ($pdo) {
            $st = $pdo->prepare('SELECT id,email,name,created_at FROM public.users WHERE id=? LIMIT 1');
            $st->execute([$localId]);
            $row = $st->fetch();
            if ($row) {
                $_SESSION['user_id'] = (int)$row['id'];
                return [
                    'id'=>(int)$row['id'],
                    'email'=>$row['email'],
                    'name'=>$row['name'],
                    'created_at'=>$row['created_at']
                ];
            }
        }
        // Never leave a stale signed identity behind.
        clearAuthCookie();
        unset($_SESSION['user_id']);
    }

    // Bootstrap/recover the local session from Neon Auth when a valid Neon
    // session cookie is present but the local signed session is missing.
    $r=neonAuthRequest('/get-session');
    if(!$r['ok']||empty($r['data']['user'])) return null;

    $local=localUserFromNeon((array)$r['data']['user']);
    if($local){
        $_SESSION['user_id']=$local['id'];
        setAuthCookie((int)$local['id']);
        return $local;
    }
    return null;
}
function getWalletBalance(int $userId): float {
    $pdo=getDb(); if(!$pdo)return 0.0;
    $st=$pdo->prepare('SELECT balance FROM wallets WHERE user_id=?'); $st->execute([$userId]); $row=$st->fetch();
    return $row?(float)$row['balance']:0.0;
}
function getWalletTransactions(int $userId): array {
    $pdo=getDb(); if(!$pdo)return [];
    $list=[];
    $st=$pdo->prepare('SELECT reference,amount,status,created_at,completed_at FROM fund_requests WHERE user_id=? ORDER BY created_at DESC'); $st->execute([$userId]);
    while($row=$st->fetch()) $list[]=['date'=>$row['created_at'],'type'=>'credit','description'=>'Wallet funding (SprintPay)','reference'=>$row['reference'],'amount'=>(float)$row['amount'],'status'=>$row['status']==='completed'?'confirmed':'pending'];
    $st=$pdo->prepare('SELECT id,total_amount,created_at,product_name FROM orders WHERE user_id=? ORDER BY created_at DESC'); $st->execute([$userId]);
    while($row=$st->fetch()) $list[]=['date'=>$row['created_at'],'type'=>'debit','description'=>'Order #'.$row['id'].' – '.$row['product_name'],'reference'=>(string)$row['id'],'amount'=>-(float)$row['total_amount'],'status'=>'confirmed'];
    usort($list,fn($a,$b)=>strcmp((string)$b['date'],(string)$a['date'])); return $list;
}
function getWalletTransactionsPaginated(int $userId,int $page=1,int $perPage=20): array {
    $all=getWalletTransactions($userId); $total=count($all); $totalPages=$perPage>0?(int)ceil($total/$perPage):1; $page=max(1,min($page,max(1,$totalPages)));
    return ['items'=>array_slice($all,($page-1)*$perPage,$perPage),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$totalPages];
}
function deductWalletBalance(int $userId,float $amount): bool {
    if($amount<=0)return true; $pdo=getDb(); if(!$pdo)return false;
    $st=$pdo->prepare('UPDATE wallets SET balance=balance-?,updated_at=CURRENT_TIMESTAMP WHERE user_id=? AND balance>=?'); $st->execute([$amount,$userId,$amount]); return $st->rowCount()>0;
}
function requireLogin(): void {
    if(getCurrentUser()===null){header('Location: login.php?redirect='.urlencode($_SERVER['REQUEST_URI']??'index.php'));exit;}
}
function loginUser(string $email,string $password): bool {
    $r=neonAuthRequest('/sign-in/email',['email'=>strtolower(trim($email)),'password'=>$password,'rememberMe'=>true,'callbackURL'=>appOrigin() . '/index.php']);
    if(!$r['ok']){error_log('Neon Auth login failed: '.($r['error']??'unknown'));return false;}
    $user=$r['data']['user']??null;
    if(!is_array($user)){ $s=neonAuthRequest('/get-session'); $user=$s['data']['user']??null; }
    $local=is_array($user)?localUserFromNeon($user):null;
    if(!$local)return false;
    session_regenerate_id(true);
    $_SESSION['user_id']=$local['id'];
    setAuthCookie((int)$local['id']);
    session_write_close();
    return true;
}
function registerUser(string $email,string $password,string $name): ?string {
    $r=neonAuthRequest('/sign-up/email',['name'=>trim($name),'email'=>strtolower(trim($email)),'password'=>$password,'callbackURL'=>appOrigin() . '/index.php']);
    if(!$r['ok'])return (string)($r['error']??'Registration failed.');
    $user=$r['data']['user']??null;
    if(!is_array($user)){ $s=neonAuthRequest('/get-session'); $user=$s['data']['user']??null; }
    $local=is_array($user)?localUserFromNeon($user):null;
    if(!$local)return 'Account created, but the account profile could not be initialized.';
    session_regenerate_id(true);
    $_SESSION['user_id']=$local['id'];
    setAuthCookie((int)$local['id']);
    session_write_close();
    return null;
}
function logoutUser(): void {
    neonAuthRequest('/sign-out');
    $_SESSION=[]; clearAuthCookie();
    if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}
    session_destroy();
}
function getSetting(string $key): ?string {
    $pdo=getDb(); if(!$pdo)return null; $st=$pdo->prepare('SELECT value FROM settings WHERE key=?');$st->execute([$key]);$row=$st->fetch();
    if($row&&$row['value']!==''&&$row['value']!==null)return(string)$row['value'];
    $map=['site_title'=>'SITE_TITLE','business_name'=>'BUSINESS_NAME','logo_url'=>'LOGO_URL','markup_percent'=>'MARKUP_PERCENT','admin_extra_amount'=>null,'sprintpay_enabled'=>'SPRINTPAY_ENABLED','sprintpay_merchant_id'=>'SPRINTPAY_MERCHANT_ID','sprintpay_callback_url'=>'SPRINTPAY_CALLBACK_URL'];
    $c=$map[$key]??null; if($c&&defined($c)){ $v=constant($c); return $v===false?'0':(string)$v; } return null;
}
function setSetting(string $key,string $value): bool {
    $pdo=getDb();if(!$pdo)return false;$st=$pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value');$st->execute([$key,$value]);return true;
}
function recordOrder(?int $userId,int $productId,string $productName,int $qty,float $unitPrice,string $apiOrderId='',string $productDetails=''): void {
    $pdo=getDb();if(!$pdo)return;$total=round($unitPrice*$qty,2);$st=$pdo->prepare('INSERT INTO orders(user_id,product_id,product_name,qty,unit_price,total_amount,api_order_id,product_details) VALUES(?,?,?,?,?,?,?,?)');$st->execute([$userId,$productId,$productName,$qty,$unitPrice,$total,$apiOrderId,$productDetails]);
}
function getOrderById(int $orderId): ?array {
    $pdo=getDb();if(!$pdo)return null;$st=$pdo->prepare('SELECT * FROM orders WHERE id=?');$st->execute([$orderId]);$row=$st->fetch();return$row?:null;
}
function getOrdersByUser(int $userId): array {
    $pdo=getDb();if(!$pdo)return[];$st=$pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');$st->execute([$userId]);return$st->fetchAll();
}
function getOrdersByUserPaginated(int $userId,int $page=1,int $perPage=15): array {
    $pdo=getDb();if(!$pdo)return['items'=>[],'total'=>0,'page'=>1,'per_page'=>$perPage,'total_pages'=>0];$st=$pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=?');$st->execute([$userId]);$total=(int)$st->fetchColumn();$pages=$perPage>0?(int)ceil($total/$perPage):1;$page=max(1,min($page,max(1,$pages)));$st=$pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?');$st->bindValue(1,$userId,PDO::PARAM_INT);$st->bindValue(2,$perPage,PDO::PARAM_INT);$st->bindValue(3,($page-1)*$perPage,PDO::PARAM_INT);$st->execute();return['items'=>$st->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$pages];
}
function reportOrder(int $orderId,int $userId,string $reason): ?string {
    $pdo=getDb();if(!$pdo)return'Database error.';$o=getOrderById($orderId);if(!$o||(int)$o['user_id']!==$userId)return'Order not found.';if(!empty($o['reported_at']))return'Order already reported.';$created=strtotime((string)$o['created_at']);if($created===false||time()-$created>7200)return'Report is only allowed within 2 hours of purchase.';$st=$pdo->prepare('UPDATE orders SET reported_at=CURRENT_TIMESTAMP,report_reason=? WHERE id=? AND user_id=?');$st->execute([$reason,$orderId,$userId]);return$st->rowCount()>0?null:'Update failed.';
}
function getReportedOrders(): array {
    $pdo=getDb();if(!$pdo)return[];$st=$pdo->query('SELECT o.*,u.email,u.name FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.reported_at IS NOT NULL ORDER BY o.reported_at DESC');return$st?$st->fetchAll():[];
}
function setOrderReplaced(int $orderId,string $note): bool {
    $pdo=getDb();if(!$pdo)return false;$st=$pdo->prepare("UPDATE orders SET replacement_status='replaced',replacement_note=?,replaced_at=CURRENT_TIMESTAMP WHERE id=?");$st->execute([$note,$orderId]);return$st->rowCount()>0;
}
function createFundRequest(int $userId,float $amount): ?string {
    $pdo=getDb();if(!$pdo||$amount<=0)return null;$ref='ref_'.$userId.'_'.time().'_'.bin2hex(random_bytes(4));$st=$pdo->prepare('INSERT INTO fund_requests(user_id,amount,reference,status) VALUES(?,?,?,?)');$st->execute([$userId,$amount,$ref,'pending']);return$st->rowCount()>0?$ref:null;
}
function completeFundRequestByReference(string $reference,float $amount): bool {
    $pdo=getDb();if(!$pdo)return false;
    try{$pdo->beginTransaction();$st=$pdo->prepare('SELECT id,user_id,amount,status FROM fund_requests WHERE reference=? FOR UPDATE');$st->execute([$reference]);$row=$st->fetch();if(!$row||$row['status']!=='pending'){if($pdo->inTransaction())$pdo->rollBack();return false;}$userId=(int)$row['user_id'];$requestAmount=(float)$row['amount'];if($amount<=0||abs($amount-$requestAmount)>0.01)$amount=$requestAmount;$pdo->prepare('INSERT INTO wallets(user_id,balance) VALUES(?,0) ON CONFLICT(user_id) DO NOTHING')->execute([$userId]);$pdo->prepare('UPDATE wallets SET balance=balance+?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?')->execute([$amount,$userId]);$pdo->prepare("UPDATE fund_requests SET status='completed',completed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$row['id']]);$pdo->commit();return true;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Fund completion failed: '.$e->getMessage());return false;}
}
