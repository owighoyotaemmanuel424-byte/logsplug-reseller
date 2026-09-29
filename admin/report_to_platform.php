<?php
/**
 * Report a reseller order to the main site so platform admin can replace the product.
 * POST: order_id (reseller local order id)
 */
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    header('Location: reported_orders.php');
    exit;
}

$resellerOrderId = (int) $_POST['order_id'];
$order = getOrderById($resellerOrderId);
if (!$order || empty($order['api_order_id']) || empty($order['reported_at'])) {
    header('Location: reported_orders.php?error=' . urlencode('Order not found or not reported locally.'));
    exit;
}

$apiOrderId = (int) $order['api_order_id'];
$reason = trim($order['report_reason'] ?? '');

$apiKey = defined('RESELLER_API_KEY') ? RESELLER_API_KEY : '';
$baseUrl = rtrim(defined('API_BASE_URL') ? API_BASE_URL : '', '/');
if ($apiKey === '' || $baseUrl === '') {
    header('Location: reported_orders.php?error=' . urlencode('API not configured.'));
    exit;
}

$ch = curl_init($baseUrl . '/api/reseller/report-order');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['order_id' => $apiOrderId, 'reason' => $reason]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-Api-Key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = $res ? json_decode($res, true) : [];
if ($code === 200 && !empty($data['success'])) {
    header('Location: reported_orders.php?sent=1');
    exit;
}

$message = $data['message'] ?? 'Request failed.';
header('Location: reported_orders.php?error=' . urlencode($message));
exit;
