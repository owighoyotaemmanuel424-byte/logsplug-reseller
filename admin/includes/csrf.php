<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function csrf_token(): string
{
    if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32));
    return (string)$_SESSION['_csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES,'UTF-8').'">';
}
function require_csrf(): void
{
    if($_SERVER['REQUEST_METHOD']!=='POST') return;
    $token=(string)($_POST['_csrf']??'');
    if($token===''||!hash_equals(csrf_token(),$token)){
        http_response_code(419); exit('Invalid CSRF token.');
    }
}
