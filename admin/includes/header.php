<?php
$currentAdminPage = isset($currentAdminPage) ? $currentAdminPage : '';
$resellerBalanceForNav = function_exists('getResellerPlatformBalance') ? getResellerPlatformBalance() : null;
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Admin navigation">
    <div class="admin-sidebar-inner">
        <div class="admin-brand-row">
            <button class="admin-mobile-close" id="adminMobileClose" type="button" aria-label="Close navigation">×</button>
            <a class="admin-brand" href="index.php" aria-label="Reseller Admin dashboard">
                <span class="admin-brand-mark">LP</span>
                <span class="admin-brand-text">Reseller Admin</span>
            </a>
        </div>

        <div class="admin-sidebar-balance">
            <span class="admin-sidebar-balance-label">Reseller balance</span>
            <span class="admin-sidebar-balance-value"><?php echo $resellerBalanceForNav !== null ? '₦' . number_format($resellerBalanceForNav, 2) : '—'; ?></span>
        </div>

        <nav class="admin-nav">
            <a href="index.php" class="<?php echo $currentAdminPage === 'dashboard' ? 'active' : ''; ?>" title="Dashboard">
                <span class="admin-nav-icon">⌂</span><span class="admin-nav-label">Dashboard</span>
            </a>
            <a href="settings.php" class="<?php echo $currentAdminPage === 'settings' ? 'active' : ''; ?>" title="Site & SprintPay">
                <span class="admin-nav-icon">⚙</span><span class="admin-nav-label">Site &amp; SprintPay</span>
            </a>
            <a href="users.php" class="<?php echo $currentAdminPage === 'users' ? 'active' : ''; ?>" title="Customers & wallets">
                <span class="admin-nav-icon">♙</span><span class="admin-nav-label">Customers &amp; wallets</span>
            </a>
            <a href="funding.php" class="<?php echo $currentAdminPage === 'funding' ? 'active' : ''; ?>" title="Funding">
                <span class="admin-nav-icon">₦</span><span class="admin-nav-label">Funding</span>
            </a>
            <a href="orders.php" class="<?php echo $currentAdminPage === 'orders' ? 'active' : ''; ?>" title="Orders">
                <span class="admin-nav-icon">▤</span><span class="admin-nav-label">Orders</span>
            </a>
            <a href="reported_orders.php" class="<?php echo $currentAdminPage === 'reported' ? 'active' : ''; ?>" title="Reported orders">
                <span class="admin-nav-icon">!</span><span class="admin-nav-label">Reported orders</span>
            </a>
            <a href="../index.php" target="_blank" rel="noopener" title="View store">
                <span class="admin-nav-icon">↗</span><span class="admin-nav-label">View store</span>
            </a>
        </nav>

        <div class="admin-sidebar-footer">
            <a href="logout.php" title="Logout">
                <span class="admin-nav-icon">⇥</span><span class="admin-nav-label">Logout</span>
            </a>
        </div>
    </div>
</aside>

<div class="admin-sidebar-scrim" id="adminSidebarScrim" aria-hidden="true"></div>

<div class="admin-main">
    <header class="admin-header">
        <button class="admin-sidebar-toggle" id="adminSidebarToggle" type="button" aria-controls="adminSidebar" aria-expanded="true" aria-label="Collapse sidebar">
            <span></span><span></span><span></span>
        </button>
        <span class="admin-page-title"><?php echo htmlspecialchars($adminPageTitle ?? 'Admin'); ?></span>
        <div class="admin-header-spacer" aria-hidden="true"></div>
    </header>
    <main class="admin-content">

<script>
(function () {
    var body = document.body;
    var toggle = document.getElementById('adminSidebarToggle');
    var scrim = document.getElementById('adminSidebarScrim');
    var mobileClose = document.getElementById('adminMobileClose');
    var storageKey = 'logsplug-admin-sidebar-collapsed';

    function isMobile() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function setCollapsed(collapsed, persist) {
        if (isMobile()) {
            body.classList.toggle('admin-sidebar-mobile-open', !collapsed);
            if (toggle) {
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? 'Open sidebar' : 'Close sidebar');
            }
        } else {
            body.classList.toggle('admin-sidebar-collapsed', collapsed);
            if (toggle) {
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            }
            if (persist) {
                try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (e) {}
            }
        }
    }

    var saved = false;
    try { saved = localStorage.getItem(storageKey) === '1'; } catch (e) {}
    setCollapsed(saved, false);

    if (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobile()) {
                var open = body.classList.contains('admin-sidebar-mobile-open');
                setCollapsed(open, false);
            } else {
                setCollapsed(!body.classList.contains('admin-sidebar-collapsed'), true);
            }
        });
    }

    if (scrim) {
        scrim.addEventListener('click', function () {
            setCollapsed(true, false);
        });
    }

    if (mobileClose) {
        mobileClose.addEventListener('click', function () {
            setCollapsed(true, false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isMobile() && body.classList.contains('admin-sidebar-mobile-open')) {
            setCollapsed(true, false);
        }
    });

    document.querySelectorAll('.admin-nav a, .admin-sidebar-footer a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) setCollapsed(true, false);
        });
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            body.classList.remove('admin-sidebar-mobile-open');
            setCollapsed(saved, false);
        }
    });
})();
</script>
