<?php
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';
if (getDb() === null) {
    header('Location: index.php');
    exit;
}
$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Store'));
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$currentUser = getCurrentUser();
$dbPath = defined('DB_PATH') ? DB_PATH : '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($email === '' || $pass === '') {
        $error = 'Email and password required.';
    } elseif (loginUser($email, $pass)) {
        $redirect = $_GET['redirect'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
if ($currentUser) {
    header('Location: index.php');
    exit;
}
$pageTitle = 'Login - ' . htmlspecialchars($businessName);
$layout = 'narrow';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">Login</h1>
    <div class="auth-card">
        <?php if ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <p class="auth-links">
            <a href="register.php">Register</a><span>|</span><a href="index.php">Back to store</a>
        </p>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
