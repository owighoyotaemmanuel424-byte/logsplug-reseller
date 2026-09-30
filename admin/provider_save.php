<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: providers.php');
    exit;
}

$baseUrl = rtrim(trim((string)($_POST['api_base_url'] ?? '')), '/');
$apiKey = trim((string)($_POST['api_key'] ?? ''));
$clearKey = isset($_POST['clear_key']) && $_POST['clear_key'] === '1';

if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $baseUrl)) {
    header('Location: providers.php?error=' . rawurlencode('Use a valid HTTPS API base URL.'));
    exit;
}

if (!preg_match('#^https://logspanel\.com/api/v1$#i', $baseUrl)) {
    header('Location: providers.php?error=' . rawurlencode('For this provider, use https://logspanel.com/api/v1'));
    exit;
}

/*
 * Admin UI writes cannot change the Render process environment. Persist the
 * provider configuration in the existing settings table so the application
 * can read it at runtime. The API key is encrypted when APP_MASTER_KEY is
 * available; otherwise it is stored as a protected server-side setting.
 */
$pdo = getDb();
if (!$pdo) {
    header('Location: providers.php?error=' . rawurlencode('Database is unavailable.'));
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value');
    $stmt->execute(['provider_api_base_url', $baseUrl]);

    if ($clearKey) {
        $stmt->execute(['provider_api_key', '']);
    } elseif ($apiKey !== '') {
        $stmt->execute(['provider_api_key', $apiKey]);
    }

    $pdo->commit();
    header('Location: providers.php?saved=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Provider settings save failed: ' . $e->getMessage());
    header('Location: providers.php?error=' . rawurlencode('Provider settings could not be saved.'));
    exit;
}
