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

function isAdminSetup(): bool {
    $hash = function_exists('getSetting') ? getSetting('admin_password_hash') : null;
    return $hash === null || $hash === '';
}

function adminLogin(string $password): bool {
    if (!function_exists('getSetting')) return false;
    foreach (['admin' => 'admin_password_hash', 'reseller' => 'reseller_password_hash'] as $role => $key) {
        $hash = getSetting($key);
        if ($hash !== null && $hash !== '' && password_verify($password, $hash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_role'] = $role;
            return true;
        }
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
