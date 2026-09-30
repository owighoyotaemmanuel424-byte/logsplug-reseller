<?php
require_once __DIR__ . '/../admin_helpers.php';
require_once __DIR__ . '/includes/csrf.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_csrf();

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pdo = getDb();
$noDb = $pdo === null;
$error = '';

// Admin credentials are verified from environment configuration on POST.
// Avoid a database/bootstrap round-trip on the initial login-page render.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noDb) {
    $email = (string)($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid admin email address.';
    } elseif ($password === '') {
        $error = 'Enter your admin password.';
    } elseif (!adminLogin($email, $password)) {
        $error = 'Invalid admin email or password.';
    } else {
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
    <title>Admin login – Reseller Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    </head>
<body>
<div class="site-wrap narrow admin-login-card">
    <div class="auth-card">
        <div class="admin-login-brand">
            <h1 class="page-title">Admin login</h1>
            <p class="admin-login-subtitle">Secure access to the reseller administration panel.</p>
        </div>

        <?php if ($noDb): ?>
            <div class="alert alert-error"><p>Database is not configured or cannot be reached.</p></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>

        <?php if (!$noDb): ?>
        <form method="post" class="admin-form" autocomplete="off"><?=csrf_field()?>
            <div class="form-group">
                <label for="email">Admin email</label>
                <input type="email" id="email" name="email" required autocomplete="username"
                       value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Sign in</button>
            <p class="text-muted" style="margin-top:12px;text-align:center;">
                <a href="recover.php">Forgot admin password?</a>
            </p>
            <p class="text-muted" style="margin-top:10px;text-align:center;">
                <a href="master-login.php">Use admin master key</a>
            </p>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
