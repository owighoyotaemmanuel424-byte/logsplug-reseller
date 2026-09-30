<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
requireAdmin();
$id=strtolower(trim((string)($_GET['id']??'logspanel')));
$schema=ProviderRegistry::schema($id);$config=ProviderRegistry::config($id);
require __DIR__.'/includes/head.php';require __DIR__.'/includes/header.php';
?>
<div class="admin-card"><h1><?=htmlspecialchars((string)$schema['label'])?></h1>
<?php if(isset($_GET['saved'])):?><div class="alert alert-success">Provider settings saved.</div><?php endif;?>
<?php if(isset($_GET['error'])):?><div class="alert alert-error"><?=htmlspecialchars((string)$_GET['error'])?></div><?php endif;?>
<form method="post" action="provider_save.php" autocomplete="off">
<input type="hidden" name="provider_id" value="<?=htmlspecialchars($id)?>">
<?=csrf_field()?>
<?php foreach(($schema['fields']??[]) as $field):$name=(string)$field['name'];$type=(string)($field['type']??'text');$value=(string)($config[$name]??$field['default']??'');?>
<label for="<?=htmlspecialchars($name)?>"><?=htmlspecialchars((string)$field['label'])?></label>
<?php if($type==='boolean'):?><input id="<?=htmlspecialchars($name)?>" name="<?=htmlspecialchars($name)?>" type="checkbox" value="1" <?=$value==='1'?'checked':''?>><?php else:?><input id="<?=htmlspecialchars($name)?>" name="<?=htmlspecialchars($name)?>" type="<?=in_array($type,['url','email','text'],true)?$type:'text'?>" value="<?=htmlspecialchars($value)?>" <?=$field['required']??false?'required':''?>><?php endif;?>
<?php endforeach;?>
<label for="secret">Provider secret</label><input id="secret" name="secret" type="password" autocomplete="new-password" placeholder="Leave blank to keep the saved secret">
<button class="btn btn-primary" type="submit">Save provider</button>
</form></div>
<?php require __DIR__.'/includes/footer.php';?>