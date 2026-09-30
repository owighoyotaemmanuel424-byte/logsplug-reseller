<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_once __DIR__.'/auth_helpers.php';
require_once __DIR__.'/includes/product_api.php';
require_once __DIR__.'/includes/order_service.php';
require_once __DIR__.'/includes/naira.php';

$user=getCurrentUser();$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['product_ref'])){
 if(!$user){header('Location: login.php?redirect='.rawurlencode('index.php'));exit;}
 $ref=trim((string)$_POST['product_ref']);$qty=max(1,min(100,(int)($_POST['qty']??1)));
 $result=fetchResellerProductsFast();
 $selected=null;
 foreach($result['products'] as $p)if(hash_equals((string)$p['product_ref'],$ref)){$selected=$p;break;}
 if(!$selected)$error='Selected service is no longer available.';
 elseif(!(bool)$selected['purchasable'])$error='This service is currently unavailable.';
 else{
  try{
   $markup=getSetting('markup_percent')??MARKUP_PERCENT;
   $order=createCustomerOrder((int)$user['id'],$selected,$qty,(string)$markup);
   header('Location: my_orders.php?ordered=1');exit;
  }catch(Throwable $e){$error=$e->getMessage();}
 }
}
$catalog=fetchResellerProductsFast();$products=$catalog['products'];
$markup=getSetting('markup_percent')??MARKUP_PERCENT;
$businessName=getSetting('business_name')??BUSINESS_NAME;$pageTitle=$businessName;
require __DIR__.'/includes/head.php';require __DIR__.'/includes/header.php';
?>
<section class="landing-hero">
 <div class="landing-hero__content"><span class="landing-eyebrow">FAST • SECURE • SIMPLE</span><h1>Your digital services, <span>made simple.</span></h1>
 <p>Browse live services, fund your wallet, place orders and track delivery from one account.</p>
 <div class="landing-actions"><a href="services.php" class="btn btn-primary btn-lg">Browse services</a><?php if(!$user):?><a href="register.php" class="btn btn-secondary btn-lg">Create account</a><?php endif;?></div></div>
</section>
<?php if($error):?><div class="alert alert-error"><p><?=htmlspecialchars($error)?></p></div><?php endif;?>
<?php if($catalog['error']!==''):?><div class="alert alert-error"><p><?=htmlspecialchars($catalog['error'])?></p></div><?php endif;?>
<section class="landing-shop-heading"><div><span class="landing-section-kicker">SERVICES</span><h2>Available products</h2><p>Prices use the provider selling price plus your configured local markup. No double markup.</p></div></section>
<div class="reseller-toolbar"><input type="search" id="product-search" class="reseller-search" placeholder="Search products..." aria-label="Search products"></div>
<?php if(!$products):?><div class="card"><p>No services are available right now.</p></div><?php else:?>
<div class="reseller-product-grid" id="product-grid">
<?php foreach($products as $p):$unit=customerUnitPrice((string)$p['selling_price'],(string)$markup);?>
 <article class="reseller-product-card product-card" data-name="<?=htmlspecialchars(strtolower((string)$p['name']))?>">
  <div class="reseller-product-card__body"><h3 class="reseller-product-card__title"><?=htmlspecialchars((string)$p['name'])?></h3>
  <p><?=htmlspecialchars((string)($p['description']??''))?></p>
  <div class="reseller-product-card__meta"><span class="card__pill"> <?=htmlspecialchars(nairaFormat($unit))?> </span><span class="card__pill">Stock: <?= (int)$p['available_quantity']?></span></div>
  <?php if($user&&$p['available_quantity']>0):?><form method="post" class="reseller-product-card__form">
   <input type="hidden" name="product_ref" value="<?=htmlspecialchars((string)$p['product_ref'])?>">
   <input type="number" name="qty" value="1" min="<?=max(1,(int)$p['min_quantity'])?>" max="<?=min(100,(int)$p['max_quantity'])?>" class="qty-input" required>
   <button class="btn btn-primary" type="submit">Buy</button>
  </form><?php elseif(!$user):?><a class="btn btn-primary" href="login.php?redirect=<?=rawurlencode('index.php')?>">Login to buy</a><?php else:?><span class="text-muted">Out of stock</span><?php endif;?>
  </div>
 </article>
<?php endforeach;?></div>
<script>
(function(){const q=document.getElementById('product-search');const cards=document.querySelectorAll('.product-card');if(q)q.addEventListener('input',function(){const s=q.value.toLowerCase().trim();cards.forEach(c=>c.style.display=(!s||(c.dataset.name||'').includes(s))?'':'none');});})();
</script>
<?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
