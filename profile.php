<?php
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';
requireLogin();
$user = getCurrentUser();
$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Store'));
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$currentUser = $user;
$dbPath = defined('DB_PATH') ? DB_PATH : '';
$pageTitle = 'Profile - ' . htmlspecialchars($businessName);
$layout = 'narrow';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">My Profile</h1>
    <div class="card">
        <ul class="profile-list">
            <li><strong>Name</strong> <?php echo htmlspecialchars($user['name']); ?></li>
            <li><strong>Email</strong> <?php echo htmlspecialchars($user['email']); ?></li>
            <li><strong>Member since</strong> <?php echo htmlspecialchars($user['created_at']); ?></li>
        </ul>
    </div>
    <p class="auth-links">
        <a href="wallet.php">Wallet</a><span>|</span><a href="index.php">Store</a><span>|</span><a href="logout.php">Logout</a>
    </p>

<?php require __DIR__ . '/includes/footer.php'; ?>
