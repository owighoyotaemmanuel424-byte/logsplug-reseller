<?php
require_once __DIR__ . '/../admin_helpers.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pdo = function_exists('getDb') ? getDb() : null;
$noDb = !defined('DATABASE_URL') || trim((string) DATABASE_URL) === '' || $pdo === null;
$setup = !$noDb && isAdminSetup();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noDb) {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($setup) {
        // Canonical login page also serves as the first-admin bootstrap/recovery
        // screen when no credential exists. This avoids redirect loops between
        // login.php and setup.php while preserving the one-time setup rule.
        if (strlen($password) < 12) {
            $error = 'Admin password must be at least 12 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!createAdminPassword($password)) {
            // A concurrent request may have created the admin. Re-check and
            // authenticate against the credential that won the race.
            if (!isAdminSetup() && adminLogin($password)) {
                header('Location: index.php');
                exit;
            }
            $error = 'Unable to create the admin account. Please try again.';
        } else {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_role'] = 'admin';
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        }
    } elseif (adminLogin($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid password.';
    }
}

$adminPageTitle = 'Admin login';

$adminPageTitle = $setup ? 'Set admin password' : 'Admin login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $setup ? 'Setup' : 'Login'; ?> – Reseller Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="site-wrap narrow" style="margin-top: 60px;">
    <div class="auth-card">
        <h1 class="page-title">Admin login</h1>
        <?php if ($noDb): ?>
            <div class="alert alert-error"><p>Database is not configured or cannot be reached. Set the <strong>DATABASE_URL</strong> environment variable in Render.</p></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        <?php if (!$noDb): ?>
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="current-password">
            </div>
            <?php if ($setup): ?>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="12" autocomplete="new-password">
                <p class="text-muted">If this installation has no admin credential yet, the password will initialize it securely and sign you in.</p>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Log in</button>
            <?php if (!$setup): ?>
            <p class="text-muted" style="margin-top:12px;"><a href="recover.php">Forgot admin password?</a></p>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
