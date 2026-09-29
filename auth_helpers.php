<?php
/**
 * Auth helpers for mini-site end-users. Requires config.php and optional DB_PATH.
 */
if (!defined('RESELLER_API_KEY')) {
    require_once __DIR__ . '/config.php';
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbPath = defined('DB_PATH') ? DB_PATH : '';

function getDb(): ?PDO
{
    global $dbPath;
    if ($dbPath === '' || !file_exists($dbPath)) {
        return null;
    }
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

function getCurrentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $pdo = getDb();
    if (!$pdo) {
        return null;
    }
    $st = $pdo->prepare('SELECT id, email, name, created_at FROM users WHERE id = ?');
    $st->execute([(int) $_SESSION['user_id']]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getWalletBalance(int $userId): float
{
    $pdo = getDb();
    if (!$pdo) {
        return 0.0;
    }
    $st = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ?');
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? (float) $row['balance'] : 0.0;
}

/**
 * Get wallet transaction history (funding + orders) for the user, newest first.
 * Each row: date, type ('credit'|'debit'), description, reference, amount (signed), status ('pending'|'confirmed').
 */
function getWalletTransactions(int $userId): array
{
    $pdo = getDb();
    if (!$pdo) {
        return [];
    }
    $list = [];
    $st = $pdo->prepare('SELECT reference, amount, status, created_at, completed_at FROM fund_requests WHERE user_id = ? ORDER BY created_at DESC');
    $st->execute([$userId]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $list[] = [
            'date' => $row['created_at'],
            'type' => 'credit',
            'description' => 'Wallet funding (SprintPay)',
            'reference' => $row['reference'],
            'amount' => (float) $row['amount'],
            'status' => $row['status'] === 'completed' ? 'confirmed' : 'pending',
        ];
    }
    $st = $pdo->prepare('SELECT id, total_amount, created_at, product_name FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $st->execute([$userId]);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $list[] = [
            'date' => $row['created_at'],
            'type' => 'debit',
            'description' => 'Order #' . $row['id'] . ' – ' . $row['product_name'],
            'reference' => (string) $row['id'],
            'amount' => -(float) $row['total_amount'],
            'status' => 'confirmed',
        ];
    }
    usort($list, function ($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    return $list;
}

/**
 * Get wallet transactions with pagination. Returns ['items' => array, 'total' => n, 'page' => p, 'per_page' => n, 'total_pages' => n].
 */
function getWalletTransactionsPaginated(int $userId, int $page = 1, int $perPage = 20): array
{
    $all = getWalletTransactions($userId);
    $total = count($all);
    $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $items = array_slice($all, $offset, $perPage);
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

/**
 * Deduct amount from user wallet. Returns true if deduction succeeded (balance was sufficient).
 */
function deductWalletBalance(int $userId, float $amount): bool
{
    if ($amount <= 0) {
        return true;
    }
    $pdo = getDb();
    if (!$pdo) {
        return false;
    }
    $st = $pdo->prepare('UPDATE wallets SET balance = balance - ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND balance >= ?');
    $st->execute([$amount, $userId, $amount]);
    return $st->rowCount() > 0;
}

function requireLogin(): void
{
    if (getCurrentUser() === null) {
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
}

function loginUser(string $email, string $password): bool
{
    $pdo = getDb();
    if (!$pdo) {
        return false;
    }
    $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    $_SESSION['user_id'] = (int) $row['id'];
    return true;
}

function registerUser(string $email, string $password, string $name): ?string
{
    $pdo = getDb();
    if (!$pdo) {
        return 'Database not configured.';
    }
    $st = $pdo->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $st->execute([$email, $hash, $name]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, 0)')->execute([$userId]);
        return null;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            return 'Email already registered.';
        }
        return 'Registration failed.';
    }
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Get a setting from DB (admin-editable). Falls back to config constant if key matches.
 */
function getSetting(string $key): ?string
{
    $pdo = getDb();
    if (!$pdo) {
        return null;
    }
    $st = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
    $st->execute([$key]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row !== false && $row['value'] !== '' && $row['value'] !== null) {
        return $row['value'];
    }
    $constMap = [
        'site_title' => 'SITE_TITLE',
        'business_name' => 'BUSINESS_NAME',
        'logo_url' => 'LOGO_URL',
        'markup_percent' => 'MARKUP_PERCENT',
        'admin_extra_amount' => null,
        'sprintpay_enabled' => 'SPRINTPAY_ENABLED',
        'sprintpay_merchant_id' => 'SPRINTPAY_MERCHANT_ID',
        'sprintpay_callback_url' => 'SPRINTPAY_CALLBACK_URL',
    ];
    if (isset($constMap[$key])) {
        $constName = $constMap[$key];
        if ($constName !== null && defined($constName)) {
            $v = constant($constName);
            return $v === false ? '0' : (string) $v;
        }
    }
    return null;
}

function setSetting(string $key, string $value): bool
{
    $pdo = getDb();
    if (!$pdo) {
        return false;
    }
    $st = $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    $st->execute([$key, $value]);
    return true;
}

/**
 * Record a completed order locally for admin listing.
 * $productDetails: optional text (e.g. newline-separated copy content from API delivered items).
 */
function recordOrder(?int $userId, int $productId, string $productName, int $qty, float $unitPrice, string $apiOrderId = '', string $productDetails = ''): void
{
    $pdo = getDb();
    if (!$pdo) {
        return;
    }
    $total = round($unitPrice * $qty, 2);
    try {
        $st = $pdo->prepare('INSERT INTO orders (user_id, product_id, product_name, qty, unit_price, total_amount, api_order_id, product_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$userId, $productId, $productName, $qty, $unitPrice, $total, $apiOrderId, $productDetails]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'product_details') !== false) {
            $st = $pdo->prepare('INSERT INTO orders (user_id, product_id, product_name, qty, unit_price, total_amount, api_order_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $st->execute([$userId, $productId, $productName, $qty, $unitPrice, $total, $apiOrderId]);
        } else {
            throw $e;
        }
    }
}

function getOrderById(int $orderId): ?array
{
    $pdo = getDb();
    if (!$pdo) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function getOrdersByUser(int $userId): array
{
    $pdo = getDb();
    if (!$pdo) {
        return [];
    }
    $st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $st->execute([$userId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user orders with pagination. Returns ['items' => array, 'total' => n, 'page' => p, 'per_page' => n, 'total_pages' => n].
 */
function getOrdersByUserPaginated(int $userId, int $page = 1, int $perPage = 15): array
{
    $pdo = getDb();
    if (!$pdo) {
        return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'total_pages' => 0];
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $st->execute([$userId]);
    $total = (int) $st->fetchColumn();
    $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $perPage, PDO::PARAM_INT);
    $st->bindValue(3, $offset, PDO::PARAM_INT);
    $st->execute();
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}

/** Report an order (user must own it, order must be < 2 hours old and not already reported). Returns error message or null on success. */
function reportOrder(int $orderId, int $userId, string $reason): ?string
{
    $pdo = getDb();
    if (!$pdo) {
        return 'Database error.';
    }
    $order = getOrderById($orderId);
    if (!$order || (int)$order['user_id'] !== $userId) {
        return 'Order not found.';
    }
    if (!empty($order['reported_at'])) {
        return 'Order already reported.';
    }
    $created = strtotime($order['created_at']);
    if ($created === false || (time() - $created) > 2 * 3600) {
        return 'Report is only allowed within 2 hours of purchase.';
    }
    $st = $pdo->prepare('UPDATE orders SET reported_at = datetime("now"), report_reason = ? WHERE id = ? AND user_id = ?');
    $st->execute([$reason, $orderId, $userId]);
    return $st->rowCount() > 0 ? null : 'Update failed.';
}

function getReportedOrders(): array
{
    $pdo = getDb();
    if (!$pdo) {
        return [];
    }
    $st = $pdo->query("SELECT o.*, u.email, u.name FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.reported_at IS NOT NULL ORDER BY o.reported_at DESC");
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
}

function setOrderReplaced(int $orderId, string $note): bool
{
    $pdo = getDb();
    if (!$pdo) {
        return false;
    }
    $st = $pdo->prepare("UPDATE orders SET replacement_status = 'replaced', replacement_note = ?, replaced_at = datetime('now') WHERE id = ?");
    $st->execute([$note, $orderId]);
    return $st->rowCount() > 0;
}

/**
 * Create a pending fund request and return the reference. Returns null on failure.
 */
function createFundRequest(int $userId, float $amount): ?string
{
    $pdo = getDb();
    if (!$pdo || $amount <= 0) {
        return null;
    }
    $reference = 'ref_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4));
    $st = $pdo->prepare('INSERT INTO fund_requests (user_id, amount, reference, status) VALUES (?, ?, ?, ?)');
    $st->execute([$userId, $amount, $reference, 'pending']);
    return $st->rowCount() > 0 ? $reference : null;
}

/**
 * Complete a fund request by reference: credit wallet and mark completed. Returns true on success.
 */
function completeFundRequestByReference(string $reference, float $amount): bool
{
    $pdo = getDb();
    if (!$pdo) {
        return false;
    }
    $st = $pdo->prepare('SELECT id, user_id, amount, status FROM fund_requests WHERE reference = ?');
    $st->execute([$reference]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['status'] !== 'pending') {
        return false;
    }
    $userId = (int) $row['user_id'];
    $requestAmount = (float) $row['amount'];
    if ($amount > 0 && abs($amount - $requestAmount) > 0.01) {
        $amount = $requestAmount;
        // use request amount if callback amount differs slightly
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE wallets SET balance = balance + ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?')->execute([$amount, $userId]);
        $pdo->prepare("UPDATE fund_requests SET status = 'completed', completed_at = datetime('now') WHERE id = ?")->execute([$row['id']]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
