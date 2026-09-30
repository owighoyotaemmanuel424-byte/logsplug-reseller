<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_helpers.php';

$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
$databaseConfigured = defined('DATABASE_URL') && trim((string) DATABASE_URL) !== '';
$apiKey = defined('RESELLER_API_KEY') ? RESELLER_API_KEY : '';
$baseUrl = rtrim(defined('API_BASE_URL') ? API_BASE_URL : '', '/');
$markup = (float)(function_exists('getSetting') && getSetting('markup_percent') !== null ? getSetting('markup_percent') : (defined('MARKUP_PERCENT') ? MARKUP_PERCENT : 0));
$adminExtra = (float)(function_exists('getSetting') && getSetting('admin_extra_amount') !== null ? getSetting('admin_extra_amount') : 0);
$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Store');
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : '';

$products=[]; $error='';
require_once __DIR__ . '/includes/product_api.php';
if ($apiKey && $baseUrl) {
    $productResult = fetchResellerProductsFast($baseUrl, $apiKey, 60);
    $products = $productResult['products'];
    $error = $productResult['error'];
}
$categories=[];
foreach($products as $p){
    $cat=trim((string)($p['category']??'')) ?: 'Other';
    $p['amount']=round((float)$p['reseller_price']*(1+$markup/100)+$adminExtra,2);
    $p['image_url']=(string)($p['image_url']??'');
    $categories[$cat][]=$p;
}
ksort($categories);
$pageTitle='Services - '.htmlspecialchars($businessName);
$layout='wide';
require __DIR__.'/includes/head.php';
require __DIR__.'/includes/header.php';
?>
<main class="services-page">
<section class="services-hero">
 <div><span class="services-eyebrow">SERVICE CATALOG</span><h1>Everything you need,<br><span>in one place.</span></h1><p>Browse available digital services, compare pricing and place an order securely from your wallet.</p></div>
 <div class="services-hero-card"><span>Available services</span><strong><?php echo number_format(count($products)); ?></strong><small>Updated from the service provider</small></div>
</section>
<?php if($error): ?><div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>
<div class="services-toolbar"><input id="service-search" type="search" placeholder="Search services..." aria-label="Search services"><select id="service-category" aria-label="Filter services"><option value="*">All categories</option><?php foreach(array_keys($categories) as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option><?php endforeach; ?></select></div>
<?php if(!$products): ?><div class="services-empty"><h2>No services available</h2><p>Please check back shortly.</p></div>
<?php else: foreach($categories as $cat=>$items): ?>
<section class="service-category" data-category="<?php echo htmlspecialchars($cat); ?>"><div class="service-category-head"><div><span>COLLECTION</span><h2><?php echo htmlspecialchars($cat); ?></h2></div><small><?php echo count($items); ?> services</small></div>
<div class="service-grid">
<?php foreach($items as $p): ?><article class="service-card" data-name="<?php echo htmlspecialchars(strtolower((string)$p['name'])); ?>" data-category="<?php echo htmlspecialchars($cat); ?>">
 <div class="service-image"><?php if($p['image_url']): ?><img src="<?php echo htmlspecialchars($p['image_url']); ?>" alt="" loading="lazy"><?php else: ?><span>◎</span><?php endif; ?></div>
 <div class="service-card-body"><h3><?php echo htmlspecialchars((string)$p['name']); ?></h3><div class="service-meta"><strong>₦<?php echo number_format((float)$p['amount'],2); ?></strong><span><?php echo (int)$p['in_stock']; ?> in stock</span></div>
 <?php if($currentUser): ?><form method="post" action="index.php"><input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="qty" value="1"><button class="service-buy" type="submit">Buy service <span>→</span></button></form>
 <?php else: ?><a class="service-buy" href="login.php?redirect=<?php echo urlencode('services.php'); ?>">Sign in to order <span>→</span></a><?php endif; ?></div>
</article><?php endforeach; ?>
</div></section>
<?php endforeach; endif; ?>
</main>
<style>
.services-page{padding-bottom:60px}.services-hero{display:flex;justify-content:space-between;align-items:center;gap:30px;padding:42px 40px;margin-bottom:28px;border-radius:26px;background:linear-gradient(135deg,#111827,#312e81);color:#fff;box-shadow:0 22px 55px rgba(15,23,42,.14)}.services-eyebrow,.service-category-head>div>span{font-size:.67rem;font-weight:800;letter-spacing:.16em;color:#a5b4fc}.services-hero h1{font-size:clamp(2rem,5vw,3.4rem);line-height:1.02;letter-spacing:-.05em;margin:10px 0}.services-hero h1 span{color:#c4b5fd}.services-hero p{max-width:650px;color:#cbd5e1;margin:0;line-height:1.65}.services-hero-card{min-width:190px;padding:22px;border:1px solid rgba(255,255,255,.15);border-radius:18px;background:rgba(255,255,255,.08);backdrop-filter:blur(12px)}.services-hero-card span,.services-hero-card small{display:block;color:#cbd5e1}.services-hero-card strong{display:block;font-size:2.2rem;margin:5px 0}.services-toolbar{display:flex;gap:12px;margin-bottom:30px}.services-toolbar input,.services-toolbar select{height:48px;border:1px solid #dfe3eb;border-radius:13px;background:#fff;padding:0 15px;font:inherit}.services-toolbar input{flex:1}.service-category{margin-bottom:34px}.service-category-head{display:flex;justify-content:space-between;align-items:end;margin-bottom:13px}.service-category-head h2{margin:5px 0 0;font-size:1.3rem;letter-spacing:-.025em}.service-category-head small{color:#98a2b3}.service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}.service-card{background:#fff;border:1px solid #e6e8ef;border-radius:18px;overflow:hidden;box-shadow:0 7px 25px rgba(15,23,42,.045);transition:.18s}.service-card:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(15,23,42,.09)}.service-image{height:130px;background:linear-gradient(135deg,#f5f3ff,#eef2ff);display:grid;place-items:center;overflow:hidden}.service-image img{width:100%;height:100%;object-fit:cover}.service-image span{font-size:2rem;color:#6366f1}.service-card-body{padding:17px}.service-card h3{margin:0 0 15px;font-size:.95rem;line-height:1.35;min-height:2.55em}.service-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}.service-meta strong{font-size:1.05rem}.service-meta span{font-size:.7rem;color:#667085}.service-buy{display:flex;justify-content:space-between;align-items:center;width:100%;border:0;border-radius:11px;padding:11px 13px;background:#4f46e5;color:#fff;text-decoration:none;font-weight:750;cursor:pointer}.service-buy:hover{background:#4338ca}.services-empty{text-align:center;padding:70px 20px;background:#fff;border:1px solid #e6e8ef;border-radius:20px}.services-empty p{color:#98a2b3}@media(max-width:800px){.services-hero{padding:28px 22px;flex-direction:column;align-items:flex-start}.services-hero-card{width:100%}.services-toolbar{flex-direction:column}.service-grid{grid-template-columns:1fr}.service-category-head{align-items:center}}
</style>
<?php require __DIR__.'/includes/footer.php'; ?>
