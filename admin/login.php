<?php
require_once __DIR__ . '/../admin_helpers.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$dbPath = defined('DB_PATH') ? DB_PATH : '';
if ($dbPath === '' || !function_exists('getDb') || getDb() === null) {
    $noDb = true;
} else {
    $noDb = false;
}

$error = '';
$setup = !$noDb && !isAdminSetup();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noDb) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if ($setup) {
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            setSetting('admin_password_hash', $hash);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_role'] = 'admin';
            header('Location: index.php');
            exit;
        }
    } else {
        if (adminLogin($password)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid password.';
    }
}

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
        <h1 class="page-title"><?php echo $noDb ? 'Admin' : ($setup ? 'Set admin password' : 'Admin login'); ?></h1>
        <?php if ($noDb): ?>
            <div class="alert alert-error"><p>Database not configured. Set <strong>DB_PATH</strong> in config.php to use the admin panel.</p></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        <?php if (!$noDb): ?>
        <form method="post" class="admin-form">
            <div class="form-group">
                <label for="password"><?php echo $setup ? 'Choose a password (min 8 characters)' : 'Password'; ?></label>
                <input type="password" id="password" name="password" required minlength="<?php echo $setup ? '8' : '1'; ?>">
            </div>
            <?php if ($setup): ?>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?php echo $setup ? 'Create &amp; log in' : 'Log in'; ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
