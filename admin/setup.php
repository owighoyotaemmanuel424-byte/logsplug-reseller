<?php
require_once __DIR__ . '/../admin_helpers.php';

if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pdo = getDb();
if (!$pdo) {
    http_response_code(503);
    exit('Database is not configured or cannot be reached.');
}

// Setup is permanently closed as soon as an admin password exists.
if (!isAdminSetup()) {
    header('Location: login.php');
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['admin_setup_csrf'])) {
    $_SESSION['admin_setup_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if (!hash_equals((string)$_SESSION['admin_setup_csrf'], $csrf)) {
        $error = 'Your setup session expired. Refresh the page and try again.';
    } elseif (strlen($password) < 12) {
        $error = 'Admin password must be at least 12 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->exec('SELECT pg_advisory_xact_lock(91827364)');

            // Re-check inside the transaction to reduce first-setup races.
            $check = $pdo->query('SELECT password_hash FROM admin_accounts WHERE id = 1 LIMIT 1');
            $existingHash = $check ? $check->fetchColumn() : false;

            // Migrate a legacy settings-based admin password if one exists.
            if (!is_string($existingHash) || $existingHash === '') {
                $legacy = $pdo->prepare('SELECT value FROM settings WHERE key = ? LIMIT 1');
                $legacy->execute(['admin_password_hash']);
                $existingHash = $legacy->fetchColumn();
            }

            if (is_string($existingHash) && $existingHash !== '') {
                $pdo->rollBack();
                header('Location: login.php');
                exit;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare(
                'INSERT INTO admin_accounts (id, password_hash)
                 VALUES (1, ?)
                 ON CONFLICT (id) DO UPDATE SET password_hash = EXCLUDED.password_hash,
                                                updated_at = CURRENT_TIMESTAMP'
            );
            $st->execute([$hash]);

            // Keep the legacy setting synchronized for compatibility with older code.
            $legacySt = $pdo->prepare(
                'INSERT INTO settings (key, value) VALUES (?, ?)
                 ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value'
            );
            $legacySt->execute(['admin_password_hash', $hash]);

            $pdo->commit();

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_role'] = 'admin';
            unset($_SESSION['admin_setup_csrf']);
            session_regenerate_id(true);

            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Initial admin setup failed: ' . $e->getMessage());
            $error = 'Admin setup could not be completed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First-time Admin Setup</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="site-wrap narrow" style="margin-top:60px;">
    <div class="auth-card">
        <h1 class="page-title">First-time Admin Setup</h1>
        <p class="text-muted">Create the administrator password for this installation. This setup page automatically closes after the first admin is created.</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php endif; ?>
        <form method="post" class="admin-form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['admin_setup_csrf']); ?>">
            <div class="form-group">
                <label for="password">Admin password (minimum 12 characters)</label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm admin password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="12" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Create Admin &amp; Continue</button>
        </form>
    </div>
</div>
</body>
</html>
