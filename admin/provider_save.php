<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';

requireAdmin();

if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: providers.php');exit;}
$providerId=strtolower(trim((string)($_POST['provider_id']??'logspanel')));
try{
    $schema=ProviderRegistry::schema($providerId);
    $config=[];
    foreach(($schema['fields']??[]) as $field){
        $name=(string)$field['name'];
        if($name==='')continue;
        $value=$_POST[$name]??($field['default']??'');
        if(($field['type']??'')==='boolean')$value=isset($_POST[$name])?'1':'0';
        $config[$name]=is_string($value)?trim($value):(string)$value;
    }
    $secret=array_key_exists('secret',$_POST)?trim((string)$_POST['secret']):null;
    if($secret==='')$secret=null;
    ProviderRegistry::save($providerId,$config,$secret);
    AuditLog::record('provider.save','provider',$providerId,['config_keys'=>array_keys($config),'secret_changed'=>$secret!==null]);
    header('Location: provider_edit.php?id='.rawurlencode($providerId).'&saved=1');exit;
}catch(Throwable $e){
    error_log('Provider save failed: '.$e->getMessage());
    header('Location: provider_edit.php?id='.rawurlencode($providerId).'&error='.rawurlencode('Provider settings could not be saved.'));exit;
}
