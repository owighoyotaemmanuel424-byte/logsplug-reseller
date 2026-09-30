<?php
require_once __DIR__ . '/../admin_helpers.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$configured = defined('ADMIN_MASTER_KEY') && trim((string) ADMIN_MASTER_KEY) !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterKey = (string) ($_POST['master_key'] ?? '');

    if (!$configured) {
        $error = 'Admin master-key login is not configured.';
    } elseif ($masterKey === '') {
        $error = 'Enter your admin master key.';
    } elseif (!hash_equals((string) ADMIN_MASTER_KEY, $masterKey)) {
        $error = 'Invalid admin master key.';
    } else {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_role'] = 'admin';
        $_SESSION['admin_email'] = defined('ADMIN_DEFAULT_EMAIL') ? (string) ADMIN_DEFAULT_EMAIL : '';
        $_SESSION['admin_name'] = defined('ADMIN_DEFAULT_NAME') ? (string) ADMIN_DEFAULT_NAME : 'Administrator';
        $_SESSION['admin_auth_method'] = 'master_key';

        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Admin secure access – Reseller Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .admin-login-card { max-width: 520px; margin: 7vh auto 0; }
        .admin-login-brand { text-align:center; margin-bottom:24px; }
        .admin-login-brand .page-title { margin-bottom:8px; }
        .admin-login-subtitle { color:#667085; margin:0; }
        .admin-form .btn { width:100%; }
        .master-key-note { text-align:center; margin-top:14px; font-size:.92rem; }
    </style>
</head>
<body>
<div class="site-wrap narrow admin-login-card">
    <div class="auth-card">
        <div class="admin-login-brand">
            <h1 class="page-title">Admin secure access</h1>
            <p class="admin-login-subtitle">Use your private administrator access key.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" class="admin-form" autocomplete="off">
            <div class="form-group">
                <label for="master_key">Admin master key</label>
                <input
                    type="password"
                    id="master_key"
                    name="master_key"
                    required
                    autocomplete="off"
                    autocapitalize="none"
                    spellcheck="false"
                >
            </div>
            <button type="submit" class="btn btn-primary">Secure sign in</button>
            <p class="master-key-note">
                <a href="login.php">Use email and password instead</a>
            </p>
        </form>
    </div>
</div>
</body>
</html>
