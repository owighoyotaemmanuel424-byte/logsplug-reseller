<?php
$layout = isset($layout) ? $layout : 'default';
$businessName = isset($businessName) ? $businessName : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Store');
$logoUrl = isset($logoUrl) ? $logoUrl : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$currentUser = isset($currentUser) ? $currentUser : (function_exists('getCurrentUser') ? getCurrentUser() : null);
$dbPath = isset($dbPath) ? $dbPath : (defined('DB_PATH') ? DB_PATH : '');
?>
<header class="site-header">
    <div class="site-header-inner">
        <a href="index.php" class="site-brand">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($businessName); ?>" class="site-logo">
            <?php endif; ?>
            <span class="site-title"><?php echo htmlspecialchars($businessName); ?></span>
        </a>
        <?php if ($dbPath !== ''): ?>
        <button type="button" class="site-nav-toggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="site-nav">
            <span class="site-nav-toggle__bar"></span>
            <span class="site-nav-toggle__bar"></span>
            <span class="site-nav-toggle__bar"></span>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Main">
            <?php if ($currentUser): ?>
                <?php
                $headerBalance = (function_exists('getWalletBalance')) ? getWalletBalance((int)$currentUser['id']) : 0;
                ?>
                <a href="wallet.php" class="nav-wallet" title="My Wallet">₦<?php echo number_format($headerBalance, 2); ?></a>
                <a href="my_orders.php">My Orders</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>
        <script>(function(){var t=document.querySelector('.site-nav-toggle');var n=document.getElementById('site-nav');if(t&&n){t.addEventListener('click',function(){var o=n.classList.toggle('site-nav--open');t.setAttribute('aria-expanded',o);});document.addEventListener('click',function(e){if(!t.contains(e.target)&&!n.contains(e.target)){n.classList.remove('site-nav--open');t.setAttribute('aria-expanded','false');}});}})();</script>
        <?php endif; ?>
    </div>
</header>
<div class="site-wrap <?php echo $layout === 'narrow' ? 'narrow' : ($layout === 'wide' ? 'wide' : ''); ?>">
