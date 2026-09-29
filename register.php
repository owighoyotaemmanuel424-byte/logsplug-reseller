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
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $name = trim($_POST['name'] ?? '');
    if ($email === '' || $password === '' || $name === '') {
        $error = 'All fields required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $err = registerUser($email, $password, $name);
        if ($err === null) {
            $success = true;
            if (loginUser($email, $password)) {
                header('Location: index.php');
                exit;
            }
        } else {
            $error = $err;
        }
    }
}
if ($currentUser) {
    header('Location: index.php');
    exit;
}
$pageTitle = 'Register - ' . htmlspecialchars($businessName);
$layout = 'narrow';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">Register</h1>
    <div class="auth-card">
        <?php if ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="alert alert-success">Account created. Redirecting...</p>
        <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" placeholder="Your name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Password (min 6 characters)</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
        <?php endif; ?>
        <p class="auth-links">
            <a href="login.php">Login</a><span>|</span><a href="index.php">Back to store</a>
        </p>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
