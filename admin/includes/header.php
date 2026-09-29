<?php
$currentAdminPage = isset($currentAdminPage) ? $currentAdminPage : '';
$resellerBalanceForNav = function_exists('getResellerPlatformBalance') ? getResellerPlatformBalance() : null;
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-inner">
        <div class="brand">Reseller Admin</div>
        <div class="admin-sidebar-balance">
            <span class="admin-sidebar-balance-label">Reseller balance</span>
            <span class="admin-sidebar-balance-value"><?php echo $resellerBalanceForNav !== null ? '₦' . number_format($resellerBalanceForNav, 2) : '—'; ?></span>
        </div>
        <a href="index.php" class="<?php echo $currentAdminPage === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="settings.php" class="<?php echo $currentAdminPage === 'settings' ? 'active' : ''; ?>">Site &amp; SprintPay</a>
        <a href="users.php" class="<?php echo $currentAdminPage === 'users' ? 'active' : ''; ?>">Customers &amp; wallets</a>
        <a href="funding.php" class="<?php echo $currentAdminPage === 'funding' ? 'active' : ''; ?>">Funding</a>
        <a href="orders.php" class="<?php echo $currentAdminPage === 'orders' ? 'active' : ''; ?>">Orders</a>
        <a href="reported_orders.php" class="<?php echo $currentAdminPage === 'reported' ? 'active' : ''; ?>">Reported orders</a>
        <a href="../index.php" target="_blank">View store</a>
        <a href="logout.php">Logout</a>
    </div>
</aside>
<div class="admin-main">
    <header class="admin-header">
        <span class="admin-page-title"><?php echo htmlspecialchars($adminPageTitle ?? 'Admin'); ?></span>
    </header>
    <main class="admin-content">
