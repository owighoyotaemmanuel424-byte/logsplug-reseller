<?php
$pageTitle = isset($pageTitle) ? $pageTitle : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Reseller Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
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
