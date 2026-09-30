<?php
require_once __DIR__ . '/../admin_helpers.php';

$pdo = getDb();
$error = '';
$success = '';

if (!$pdo) {
    $error = 'Database is not configured or cannot be reached.';
}

$recoveryToken = trim((string)($_ENV['ADMIN_RECOVERY_TOKEN'] ?? getenv('ADMIN_RECOVERY_TOKEN') ?: ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $token = trim((string)($_POST['recovery_token'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    if ($recoveryToken === '' || !hash_equals($recoveryToken, $token)) {
        $error = 'Invalid recovery key.';
    } elseif ($password === '' || strlen($password) < 12) {
        $error = 'New admin password must be at least 12 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // init_db.php creates both application tables before requests are served.
            // Keep recovery transactions limited to credential reads/writes so
            // schema DDL cannot poison the transaction.
            $pdo->beginTransaction();
            $pdo->exec('SELECT pg_advisory_xact_lock(91827364)');

            $used = $pdo->query("SELECT value FROM settings WHERE key = 'admin_recovery_used_at' LIMIT 1")->fetchColumn();
            if (is_string($used) && $used !== '') {
                throw new RuntimeException('This recovery key has already been used.');
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $st = $pdo->prepare(
                'INSERT INTO admin_accounts (id, password_hash)
                 VALUES (1, ?)
                 ON CONFLICT (id) DO UPDATE SET password_hash = EXCLUDED.password_hash,
                                                updated_at = CURRENT_TIMESTAMP'
            );
            $st->execute([$hash]);

            $st = $pdo->prepare(
                'INSERT INTO settings (key, value) VALUES (?, ?)
                 ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value'
            );
            $st->execute(['admin_password_hash', $hash]);
            $st->execute(['admin_recovery_used_at', gmdate('c')]);

            $pdo->commit();
            $success = 'Admin password reset successfully. You can now sign in with the new password.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Admin recovery failed: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to reset the admin password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover admin password – Reseller Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="site-wrap narrow admin-login-card">
    <div class="auth-card">
        <h1 class="page-title">Recover admin password</h1>
        <?php if ($error): ?>
            <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><p><?php echo htmlspecialchars($success); ?></p></div>
            <p><a class="btn btn-primary" href="login.php">Return to admin login</a></p>
        <?php endif; ?>
        <?php if (!$success): ?>
        <form method="post" class="admin-form" autocomplete="off">
            <div class="form-group">
                <label for="recovery_token">Recovery key</label>
                <input type="password" id="recovery_token" name="recovery_token" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="password">New admin password</label>
                <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm new password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="12" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary">Reset admin password</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
