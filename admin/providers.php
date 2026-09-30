<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/naira.php';
requireAdmin();
$adminPageTitle='Providers';$currentAdminPage='providers';
$providers=[];
foreach(ProviderRegistry::ids() as $id){
 try{$p=ProviderRegistry::get($id);$providers[]=['id'=>$id,'schema'=>$p->schema(),'health'=>$p->health(),'balance'=>$p->walletBalance()];}
 catch(Throwable $e){$providers[]=['id'=>$id,'schema'=>ProviderRegistry::schema($id),'health'=>['ok'=>false,'message'=>'Provider unavailable'],'balance'=>null];}
}
require __DIR__.'/includes/head.php';require __DIR__.'/includes/header.php';
?>
<div class="master-dashboard"><div class="master-hero"><div><span class="master-kicker">PROVIDER OPERATIONS</span><h1>Providers</h1><p>All provider integrations are discovered from their schema and implementation files.</p></div></div>
<div class="master-actions"><?php foreach($providers as $p):?><a href="provider_edit.php?id=<?=rawurlencode($p['id'])?>"><?=htmlspecialchars((string)$p['schema']['label'])?> ↗</a><?php endforeach;?></div>
<?php foreach($providers as $p):?>
<div class="admin-card" style="margin-top:16px;"><div style="display:flex;justify-content:space-between;gap:16px;align-items:center;"><div><h2 class="admin-card-title"><?=htmlspecialchars((string)$p['schema']['label'])?></h2><p><?=!empty($p['health']['ok'])?'Connected':'Not connected'?><?php if($p['balance']!==null):?> · <?=htmlspecialchars(nairaFormat((string)$p['balance']))?><?php endif;?></p></div><a class="btn btn-primary" href="provider_edit.php?id=<?=rawurlencode($p['id'])?>">Configure</a></div></div>
<?php endforeach;?>
</div>
<?php require __DIR__.'/includes/footer.php';?>
