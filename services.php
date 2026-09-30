<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/includes/logspanel_api.php';
require_once __DIR__ . '/includes/naira.php';
require_once __DIR__ . '/includes/product_api.php';
require_once __DIR__ . '/includes/providers/ProviderRegistry.php';

$currentUser = getCurrentUser();
$markup = getSetting('markup_percent') ?? MARKUP_PERCENT;
$adminExtra = getSetting('admin_extra_amount') ?? '0.00';
$businessName = getSetting('business_name') ?: BUSINESS_NAME;

$products = [];
$logsError = '';
$numberCountries = [];
$numberServices = [];
$numberError = '';
$boostCategories = [];
$boostServices = [];
$boostError = '';

// Never hit Logspanel directly from the customer catalog page. Provider catalog
// requests are cron-only so repeated page loads cannot exhaust the provider limit.
try {
    $loadCached = function(string $key): array {
        $st=db()->prepare('SELECT value FROM settings WHERE key=?');
        $st->execute([$key]);
        $raw=$st->fetchColumn();
        $d=is_string($raw)?json_decode($raw,true):null;
        return is_array($d['data']??null)?$d['data']:[];
    };
    $products=[];
    $cachedLogs=$loadCached('catalog_logspanel');
    foreach($cachedLogs as $p){
        if(!is_array($p)) continue;
        $parent=is_array($p['parent_category']??null)?$p['parent_category']:[];
        $price=(string)($p['selling_price']??$p['price']??'0');
        $products[]=['id'=>(int)($p['id']??0),'product_ref'=>(string)($p['product_ref']??''),'name'=>(string)($p['name']??'Unnamed service'),'description'=>(string)($p['description']??''),'category'=>(string)($parent['name']??$p['category']??'Other'),'selling_price'=>$price,'currency'=>(string)($p['currency']??'NGN'),'available_quantity'=>(int)($p['available_quantity']??0),'min_quantity'=>max(1,(int)($p['min_quantity']??1)),'max_quantity'=>min(100,max(1,(int)($p['max_quantity']??100))),'purchasable'=>!isset($p['purchasable'])||(bool)$p['purchasable'],'image_url'=>(string)($p['image']??$p['image_url']??'')];
    }
    $logsError=$products?'':'Catalog cache is empty. Run the catalog sync job.';
} catch(Throwable $e) { $products=[]; $logsError='Catalog is temporarily unavailable.'; }
try {
    // Reuse the cached catalog loader defined above.
    $numberCountries=$loadCached('catalog_numbers_countries');$numberServices=$loadCached('catalog_numbers_services');
    $boostCategories=$loadCached('catalog_boost_categories');$boostServices=$loadCached('catalog_boost_services');
    if(!$numberCountries) $numberError='Number catalog cache is empty. Run the catalog sync job.';
    if(!$numberServices) $numberError=$numberError?:'Number services cache is empty. Run the catalog sync job.';
    if(!$boostCategories) $boostError='Boost catalog cache is empty. Run the catalog sync job.';
    if(!$boostServices) $boostError=$boostError?:'Boost services cache is empty. Run the catalog sync job.';
} catch(Throwable $e) { $numberError=$numberError?:$e->getMessage(); }
$categories = [];
foreach ($products as $p) {
    $cat = trim((string)($p['category'] ?? '')) ?: 'Other';
    $p['amount'] = nairaAdd((string)$p['selling_price'], (string)$adminExtra);
    $p['image_url'] = (string)($p['image_url'] ?? '');
    $categories[$cat][] = $p;
}
ksort($categories);

$numberCountryNames = [];
foreach ($numberCountries as $country) {
    if (!is_array($country)) continue;
    $numberCountryNames[(string)($country['id'] ?? '')] = (string)($country['name'] ?? 'Unknown');
}

$pageTitle = 'Services - ' . htmlspecialchars($businessName);
$layout = 'wide';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>
<main class="services-page">
<section class="services-hero">
 <div><span class="services-eyebrow">COMPLETE SERVICE CATALOG</span><h1>Everything you need,<br><span>in one place.</span></h1><p>Browse the complete Logspanel catalog for logs, phone numbers and social boosting services.</p></div>
 <div class="services-hero-card"><span>Total catalog items</span><strong><?php echo number_format(count($products) + count($numberServices) + count($boostServices)); ?></strong><small>All available catalog records returned by the provider</small></div>
</section>

<div class="catalog-tabs" role="tablist" aria-label="Service catalogs">
 <button class="catalog-tab active" data-catalog="logs" type="button">Logs <b><?php echo count($products); ?></b></button>
 <button class="catalog-tab" data-catalog="numbers" type="button">Numbers <b><?php echo count($numberServices); ?></b></button>
 <button class="catalog-tab" data-catalog="boost" type="button">Boosting <b><?php echo count($boostServices); ?></b></button>
</div>

<section class="catalog-panel active" data-panel="logs">
<?php if($logsError): ?><div class="alert alert-error"><p><?php echo htmlspecialchars($logsError); ?></p></div><?php endif; ?>
<div class="services-toolbar"><input id="service-search" type="search" placeholder="Search all log products..." aria-label="Search log products"><select id="service-category" aria-label="Filter log products"><option value="*">All categories</option><?php foreach(array_keys($categories) as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option><?php endforeach; ?></select></div>
<?php if(!$products): ?><div class="services-empty"><h2>No log products available</h2><p>The complete catalog returned no purchasable products.</p></div>
<?php else: foreach($categories as $cat=>$items): ?>
<section class="service-category" data-category="<?php echo htmlspecialchars($cat); ?>"><div class="service-category-head"><div><span>LOG COLLECTION</span><h2><?php echo htmlspecialchars($cat); ?></h2></div><small><?php echo count($items); ?> products</small></div>
<div class="service-grid">
<?php foreach($items as $p): ?><article class="service-card" data-name="<?php echo htmlspecialchars(strtolower((string)$p['name'])); ?>">
 <div class="service-image"><?php if($p['image_url']): ?><img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="" loading="lazy"><?php else: ?><span>◎</span><?php endif; ?></div>
 <div class="service-card-body"><h3><?php echo htmlspecialchars((string)$p['name']); ?></h3><p class="service-description"><?php echo htmlspecialchars((string)$p['description']); ?></p><div class="service-meta"><strong><?php echo htmlspecialchars((string)$p['currency']); ?> <?php echo htmlspecialchars(nairaFormat((string)$p['amount'])); ?></strong><span><?php echo (int)$p['available_quantity']; ?> available</span></div>
 <?php if($currentUser): ?><form method="post" action="index.php"><input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="product_ref" value="<?php echo htmlspecialchars((string)($p['product_ref'] ?? '')); ?>"><input type="hidden" name="qty" value="<?php echo (int)$p['min_quantity']; ?>"><button class="service-buy" type="submit">Buy service <span>→</span></button></form>
 <?php else: ?><a class="service-buy" href="login.php?redirect=<?php echo urlencode('services.php'); ?>">Sign in to order <span>→</span></a><?php endif; ?></div>
</article><?php endforeach; ?>
</div></section>
<?php endforeach; endif; ?>
</section>

<section class="catalog-panel" data-panel="numbers">
<?php if($numberError): ?><div class="alert alert-error"><p><?php echo htmlspecialchars($numberError); ?></p></div><?php endif; ?>
<div class="catalog-summary"><strong><?php echo count($numberCountries); ?></strong><span>countries</span><strong><?php echo count($numberServices); ?></strong><span>number services</span></div>
<?php if(!$numberServices): ?><div class="services-empty"><h2>No number services available</h2><p>Number inventory may currently be unavailable.</p></div>
<?php else: ?>
<div class="service-grid">
<?php foreach($numberServices as $s): if(!is_array($s)) continue; $countryId=(string)($s['country_id']??''); $price=(string)($s['price']??'0'); $amount=nairaAdd($price,(string)$adminExtra); ?>
<article class="service-card"><div class="service-image"><span>☎</span></div><div class="service-card-body"><h3><?php echo htmlspecialchars((string)($s['service_name']??'Number service')); ?></h3><p class="service-description"><?php echo htmlspecialchars((string)($s['category']??'Activation')); ?> · <?php echo htmlspecialchars($numberCountryNames[$countryId]??(string)($s['country_name']??'Unknown country')); ?></p><div class="service-meta"><strong><?php echo htmlspecialchars((string)($s['currency']??'NGN')); ?> <?php echo htmlspecialchars(nairaFormat((string)$amount)); ?></strong><span><?php echo (int)($s['available_quantity']??0); ?> available</span></div><div class="catalog-note">Country ID: <?php echo htmlspecialchars($countryId); ?> · Service ID: <?php echo htmlspecialchars((string)($s['service_id']??'')); ?></div></div></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<section class="catalog-panel" data-panel="boost">
<?php if($boostError): ?><div class="alert alert-error"><p><?php echo htmlspecialchars($boostError); ?></p></div><?php endif; ?>
<div class="catalog-summary"><strong><?php echo count($boostCategories); ?></strong><span>boost categories</span><strong><?php echo count($boostServices); ?></strong><span>boost services</span></div>
<?php if(!$boostServices): ?><div class="services-empty"><h2>No boost services available</h2><p>Boost inventory may currently be unavailable.</p></div>
<?php else: ?>
<div class="service-grid">
<?php foreach($boostServices as $s): if(!is_array($s)) continue; ?>
<article class="service-card"><div class="service-image"><span>↗</span></div><div class="service-card-body"><h3><?php echo htmlspecialchars((string)($s['service_name']??'Boost service')); ?></h3><p class="service-description"><?php echo htmlspecialchars((string)($s['category']??'Boosting')); ?> · <?php echo htmlspecialchars((string)($s['type']??'Default')); ?></p><div class="service-meta"><strong><?php echo htmlspecialchars((string)($s['currency']??'NGN')); ?> <?php echo number_format((float)($s['rate_per_1000']??0),2); ?>/1k</strong><span><?php echo (int)($s['min']??0); ?>–<?php echo (int)($s['max']??0); ?></span></div><div class="catalog-note">Service ID: <?php echo htmlspecialchars((string)($s['service_id']??'')); ?> · Refill: <?php echo !empty($s['refill'])?'Yes':'No'; ?> · Cancel: <?php echo !empty($s['cancel'])?'Yes':'No'; ?></div></div></article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
</main>

<style>
.services-page{padding-bottom:60px}.services-hero{display:flex;justify-content:space-between;align-items:center;gap:30px;padding:42px 40px;margin-bottom:22px;border-radius:26px;background:linear-gradient(135deg,#09251e,#0b5c43);color:#fff;box-shadow:0 22px 55px rgba(15,23,42,.14)}.services-eyebrow,.service-category-head>div>span{font-size:.67rem;font-weight:800;letter-spacing:.16em;color:#8ee1c2}.services-hero h1{font-size:clamp(2rem,5vw,3.4rem);line-height:1.02;letter-spacing:-.05em;margin:10px 0}.services-hero h1 span{color:#9ee7cb}.services-hero p{max-width:650px;color:#cbd5e1;margin:0;line-height:1.65}.services-hero-card{min-width:210px;padding:22px;border:1px solid rgba(255,255,255,.15);border-radius:18px;background:rgba(255,255,255,.08);backdrop-filter:blur(12px)}.services-hero-card span,.services-hero-card small{display:block;color:#cbd5e1}.services-hero-card strong{display:block;font-size:2.2rem;margin:5px 0}.catalog-tabs{display:flex;gap:8px;margin:0 0 24px;overflow:auto;padding-bottom:3px}.catalog-tab{border:1px solid #dfe3eb;background:#fff;border-radius:12px;padding:11px 16px;font:inherit;font-weight:750;cursor:pointer;white-space:nowrap}.catalog-tab.active{background:#087f5b;color:#fff;border-color:#087f5b}.catalog-tab b{margin-left:6px;opacity:.8}.catalog-panel{display:none}.catalog-panel.active{display:block}.services-toolbar{display:flex;gap:12px;margin-bottom:30px}.services-toolbar input,.services-toolbar select{height:48px;border:1px solid #dfe3eb;border-radius:13px;background:#fff;padding:0 15px;font:inherit}.services-toolbar input{flex:1}.service-category{margin-bottom:34px}.service-category-head{display:flex;justify-content:space-between;align-items:end;margin-bottom:13px}.service-category-head h2{margin:5px 0 0;font-size:1.3rem;letter-spacing:-.025em}.service-category-head small{color:#98a2b3}.service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.service-card{background:#fff;border:1px solid #e6e8ef;border-radius:18px;overflow:hidden;box-shadow:0 7px 25px rgba(15,23,42,.045);transition:.18s}.service-card:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(15,23,42,.09)}.service-image{height:110px;background:linear-gradient(135deg,#edf8f4,#f6fbf9);display:grid;place-items:center;overflow:hidden}.service-image img{width:100%;height:100%;object-fit:cover}.service-image span{font-size:2rem;color:#087f5b}.service-card-body{padding:17px}.service-card h3{margin:0 0 9px;font-size:.95rem;line-height:1.35}.service-description{color:#667085;font-size:.78rem;line-height:1.45;min-height:2.3em;margin:0 0 13px}.service-meta{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:13px}.service-meta strong{font-size:1.02rem}.service-meta span{font-size:.7rem;color:#667085;text-align:right}.service-buy{display:flex;justify-content:space-between;align-items:center;width:100%;border:0;border-radius:11px;padding:11px 13px;background:#087f5b;color:#fff;text-decoration:none;font-weight:750;cursor:pointer}.service-buy:hover{background:#066b4d}.services-empty{text-align:center;padding:70px 20px;background:#fff;border:1px solid #e6e8ef;border-radius:20px}.services-empty p{color:#98a2b3}.catalog-summary{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:0 0 22px;padding:15px 18px;background:#fff;border:1px solid #e6e8ef;border-radius:16px}.catalog-summary strong{font-size:1.25rem}.catalog-summary span{color:#667085;margin-right:16px}.catalog-note{font-size:.7rem;color:#98a2b3;border-top:1px solid #eef0f4;padding-top:10px}@media(max-width:800px){.services-hero{padding:28px 22px;flex-direction:column;align-items:flex-start}.services-hero-card{width:100%}.services-toolbar{flex-direction:column}.service-grid{grid-template-columns:1fr}}
</style>
<script>
(function(){
  document.querySelectorAll('.catalog-tab').forEach(function(tab){
    tab.addEventListener('click',function(){
      var key=tab.getAttribute('data-catalog');
      document.querySelectorAll('.catalog-tab').forEach(function(t){t.classList.toggle('active',t===tab);});
      document.querySelectorAll('.catalog-panel').forEach(function(p){p.classList.toggle('active',p.getAttribute('data-panel')===key);});
    });
  });
  var search=document.getElementById('service-search'), select=document.getElementById('service-category');
  var sections=document.querySelectorAll('.catalog-panel[data-panel="logs"] .service-category');
  function filter(){
    var q=(search&&search.value||'').trim().toLowerCase(), cat=select?select.value:'*';
    sections.forEach(function(section){
      var matchesCat=cat==='*'||section.getAttribute('data-category')===cat, visible=0;
      section.querySelectorAll('.service-card').forEach(function(card){
        var match=!q||(card.getAttribute('data-name')||'').indexOf(q)!==-1;
        card.style.display=(matchesCat&&match)?'':'none'; if(matchesCat&&match) visible++;
      });
      section.style.display=visible?'':'none';
    });
  }
  if(search) search.addEventListener('input',filter);
  if(select) select.addEventListener('change',filter);
})();
</script>
<?php require __DIR__.'/includes/footer.php'; ?>
