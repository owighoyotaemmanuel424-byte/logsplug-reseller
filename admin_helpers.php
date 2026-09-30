<?php
if (!defined('RESELLER_API_KEY')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAdminLoggedIn(): bool { return !empty($_SESSION['admin_logged_in']); }
function isAdminRole(): bool { return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin'; }

function normalizeAdminEmail(string $email): string {
    return strtolower(trim($email));
}

function ensureDefaultAdminCredentials(): void {
    $email = defined('ADMIN_DEFAULT_EMAIL') ? normalizeAdminEmail(ADMIN_DEFAULT_EMAIL) : '';
    $password = defined('ADMIN_DEFAULT_PASSWORD') ? ADMIN_DEFAULT_PASSWORD : '';
    $name = defined('ADMIN_DEFAULT_NAME') ? trim(ADMIN_DEFAULT_NAME) : 'Administrator';
    if ($email === '' || $password === '' || strlen($password) < 12) return;

    $pdo = getDb();
    if (!$pdo) return;

    try {
        $pdo->beginTransaction();
        $pdo->exec('SELECT pg_advisory_xact_lock(91827364)');

        $used = $pdo->query("SELECT value FROM settings WHERE key = 'admin_bootstrap_used_at' LIMIT 1")->fetchColumn();
        if (is_string($used) && $used !== '') {
            $pdo->commit();
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare(
            'INSERT INTO admin_accounts (id, email, password_hash, name)
             VALUES (1, ?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET email = EXCLUDED.email,
                                            password_hash = EXCLUDED.password_hash,
                                            name = EXCLUDED.name,
                                            updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([$email, $hash, $name]);

        $st = $pdo->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value'
        );
        $st->execute(['admin_password_hash', $hash]);
        $st->execute(['admin_email', $email]);
        $st->execute(['admin_name', $name]);
        $st->execute(['admin_bootstrap_used_at', gmdate('c')]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Admin default bootstrap failed: ' . $e->getMessage());
    }
}

function getAdminCredentials(): ?array {
    $pdo = getDb();
    if (!$pdo) return null;

    try {
        $st = $pdo->query('SELECT email, password_hash, name FROM admin_accounts WHERE id = 1 LIMIT 1');
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($row) && !empty($row['password_hash'])) return $row;
    } catch (Throwable $e) {
        error_log('Admin credential table lookup warning: ' . $e->getMessage());
    }

    try {
        $legacy = getSetting('admin_password_hash');
        if (is_string($legacy) && $legacy !== '') {
            $email = normalizeAdminEmail((string) getSetting('admin_email'));
            if ($email === '') $email = defined('ADMIN_DEFAULT_EMAIL') ? normalizeAdminEmail(ADMIN_DEFAULT_EMAIL) : 'admin@localhost';
            $name = (string) getSetting('admin_name');
            if ($name === '') $name = defined('ADMIN_DEFAULT_NAME') ? ADMIN_DEFAULT_NAME : 'Administrator';
            try {
                $st = $pdo->prepare(
                    'INSERT INTO admin_accounts (id, email, password_hash, name)
                     VALUES (1, ?, ?, ?)
                     ON CONFLICT (id) DO UPDATE SET email = EXCLUDED.email,
                                                    password_hash = EXCLUDED.password_hash,
                                                    name = EXCLUDED.name,
                                                    updated_at = CURRENT_TIMESTAMP'
                );
                $st->execute([$email, $legacy, $name]);
            } catch (Throwable $migrationError) {
                error_log('Admin credential migration warning: ' . $migrationError->getMessage());
            }
            return ['email' => $email, 'password_hash' => $legacy, 'name' => $name];
        }
    } catch (Throwable $e) {
        error_log('Legacy admin credential lookup failed: ' . $e->getMessage());
    }

    return null;
}

function getAdminPasswordHash(): ?string {
    $credentials = getAdminCredentials();
    return $credentials['password_hash'] ?? null;
}

function isAdminSetup(): bool {
    return getAdminCredentials() === null;
}

function createAdminPassword(string $password, string $email = '', string $name = 'Administrator'): bool {
    if (strlen($password) < 12) return false;
    $pdo = getDb();
    if (!$pdo) return false;

    $email = normalizeAdminEmail($email);
    if ($email === '') $email = defined('ADMIN_DEFAULT_EMAIL') ? normalizeAdminEmail(ADMIN_DEFAULT_EMAIL) : 'admin@localhost';

    try {
        $pdo->beginTransaction();
        $pdo->exec('SELECT pg_advisory_xact_lock(91827364)');

        $existing = $pdo->query('SELECT password_hash FROM admin_accounts WHERE id = 1 LIMIT 1')->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            $pdo->rollBack();
            return false;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare(
            'INSERT INTO admin_accounts (id, email, password_hash, name)
             VALUES (1, ?, ?, ?)
             ON CONFLICT (id) DO NOTHING'
        );
        $st->execute([$email, $hash, trim($name) ?: 'Administrator']);

        $pdo->prepare(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value'
        )->execute(['admin_password_hash', $hash]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Admin password creation failed: ' . $e->getMessage());
        return false;
    }
}

function adminLogin(string $email, string $password): bool {
    $email = normalizeAdminEmail($email);
    $credentials = getAdminCredentials();
    if ($credentials && hash_equals(normalizeAdminEmail((string)$credentials['email']), $email)
        && password_verify($password, (string)$credentials['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_role'] = 'admin';
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_name'] = (string)$credentials['name'];
        session_regenerate_id(true);
        return true;
    }

    // Preserve the legacy reseller role for existing deployments.
    $resellerHash = getSetting('reseller_password_hash');
    if ($email !== '' && $resellerHash !== null && $resellerHash !== '' && password_verify($password, $resellerHash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_role'] = 'reseller';
        $_SESSION['admin_email'] = $email;
        session_regenerate_id(true);
        return true;
    }
    return false;
}

function adminLogout(): void { unset($_SESSION['admin_logged_in'], $_SESSION['admin_role']); }

function requireAdmin(): void {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $loginUrl = ($base !== '' ? $base . '/' : '') . 'login.php';
    if (!isAdminLoggedIn()) { header('Location: ' . $loginUrl); exit; }
}

function getAdminStats(): array {
    $pdo = getDb();
    $stats = ['users' => 0, 'orders' => 0, 'orders_today' => 0];
    if (!$pdo) return $stats;
    $st = $pdo->query('SELECT COUNT(*) FROM users');
    if ($st) $stats['users'] = (int) $st->fetchColumn();
    $st = $pdo->query('SELECT COUNT(*) FROM orders');
    if ($st) $stats['orders'] = (int) $st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= CURRENT_DATE AND created_at < CURRENT_DATE + INTERVAL '1 day'");
    if ($st) $stats['orders_today'] = (int) $st->fetchColumn();
    return $stats;
}

function saveMarkupRequest(float $requestedPercent, string $note = ''): bool {
    $pdo = getDb();
    if (!$pdo) return false;
    $st = $pdo->prepare('INSERT INTO markup_requests (requested_percent, note) VALUES (?, ?)');
    $st->execute([$requestedPercent, $note]);
    return true;
}

function getMarkupRequests(): array {
    $pdo = getDb();
    if (!$pdo) return [];
    $st = $pdo->query('SELECT id, requested_percent, note, created_at FROM markup_requests ORDER BY created_at DESC LIMIT 50');
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function getFundRequestsAll(): array {
    $pdo = getDb();
    if (!$pdo) return [];
    $st = $pdo->query('
        SELECT f.id, f.user_id, f.amount, f.reference, f.status, f.created_at, f.completed_at,
               u.email AS user_email, u.name AS user_name
        FROM fund_requests f
        LEFT JOIN users u ON u.id = f.user_id
        ORDER BY f.created_at DESC
        LIMIT 500
    ');
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function adminCreditWallet(int $userId, float $amount): bool {
    if ($amount <= 0) return false;
    $pdo = getDb();
    if (!$pdo) return false;
    $st = $pdo->prepare('
        INSERT INTO wallets (user_id, balance, updated_at)
        VALUES (?, 0, CURRENT_TIMESTAMP)
        ON CONFLICT (user_id) DO NOTHING
    ');
    $st->execute([$userId]);
    $st = $pdo->prepare('UPDATE wallets SET balance = balance + ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
    $st->execute([$amount, $userId]);
    return $st->rowCount() > 0;
}

function adminDeleteUser(int $userId): bool {
    $pdo = getDb();
    if (!$pdo) return false;
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE orders SET user_id = NULL WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM wallets WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM fund_requests WHERE user_id = ?')->execute([$userId]);
        $st = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $st->execute([$userId]);
        $pdo->commit();
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Admin delete user failed: ' . $e->getMessage());
        return false;
    }
}

function getResellerPlatformBalance(): ?float {
    $apiKey = defined('RESELLER_API_KEY') ? RESELLER_API_KEY : '';
    $baseUrl = rtrim(defined('API_BASE_URL') ? API_BASE_URL : '', '/');
    if ($apiKey === '' || $baseUrl === '') return null;
    $ch = curl_init($baseUrl . '/api/reseller/me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $apiKey],
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$res) return null;
    $data = json_decode($res, true);
    if (empty($data['success']) || !isset($data['data']['balance'])) return null;
    return (float) $data['data']['balance'];
}
