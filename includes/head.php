<?php
$pageTitle = isset($pageTitle) ? $pageTitle : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Reseller Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <script>
(function(){
  function isInternal(a){
    if(!a || !a.href || a.target === '_blank' || a.hasAttribute('download')) return false;
    try { return new URL(a.href, location.href).origin === location.origin; } catch(e){ return false; }
  }
  document.addEventListener('click',function(e){
    var a=e.target.closest ? e.target.closest('a') : null;
    if(!isInternal(a) || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var url=new URL(a.href,location.href);
    if(url.pathname === location.pathname && url.search === location.search && url.hash) return;
    document.body.classList.add('page-leaving');
  },{passive:true});
  window.addEventListener('pageshow',function(){document.body.classList.remove('page-leaving');});
})();
</script>
</head>
<body<?php echo isset($bodyClass) && $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass) . '"' : ''; ?>>
