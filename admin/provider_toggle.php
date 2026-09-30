<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/includes/audit.php';
require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed.');}
$id=strtolower(trim((string)($_POST['provider_id']??'')));$enabled=isset($_POST['enabled'])?'1':'0';
try{ProviderRegistry::save($id,['enabled'=>$enabled],null);AuditLog::record('provider.toggle','provider',$id,['enabled'=>$enabled]);header('Location: provider_edit.php?id='.rawurlencode($id).'&saved=1');}catch(Throwable $e){http_response_code(400);exit('Unable to update provider.');}