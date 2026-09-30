<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_admin();
$id=strtolower(trim((string)($_POST['provider_id']??$_GET['id']??'logspanel')));
if($_SERVER['REQUEST_METHOD']==='POST')require_csrf();
try{$h=ProviderRegistry::get($id)->health();}catch(Throwable $e){$h=['ok'=>false,'message'=>'Provider test failed.'];}
header('Content-Type: application/json; charset=utf-8');echo json_encode(['provider'=>$id,'health'=>$h],JSON_UNESCAPED_SLASHES);