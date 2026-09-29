<?php
/**
 * SprintPay callback: credit user wallet after successful payment.
 * Matches the main site’s e_fund webhook format (ApiController@e_fund).
 *
 * Expected webhook request (POST, form or JSON):
 *   - order_id  (required) – same as ref we sent to SprintPay (fund request reference)
 *   - amount    (required) – amount to credit
 *   - email     (optional) – used on main site; reseller looks up by order_id only
 *
 * Reference is read from: order_id, ref, trans_id, reference, referenceid, reference_id.
 * If order_id + amount are present, payment is treated as success (no status field needed).
 * Response: JSON with status, message, and optionally order_id, amount, credited.
 */
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';

$__reseller_callback_code = 200;
$__reseller_callback_json = ['status' => true, 'message' => 'OK'];

$pdo = getDb();
if (!$pdo) {
    $__reseller_callback_code = 503;
    $__reseller_callback_json = ['status' => false, 'message' => 'Database not configured.'];
    goto send;
}

$jsonBody = [];
$raw = file_get_contents('php://input');
if ($raw !== false && $raw !== '' && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}
$input = array_merge($jsonBody, $_GET, $_POST);
$reference = trim((string)($input['order_id'] ?? $input['ref'] ?? $input['trans_id'] ?? $input['reference'] ?? $input['referenceid'] ?? $input['reference_id'] ?? ''));
$amount = (float)($input['amount'] ?? 0);
$status = $input['status'] ?? $input['transaction_status'] ?? '';

if ($reference === '' || $amount <= 0) {
    $__reseller_callback_code = 400;
    $__reseller_callback_json = ['status' => false, 'message' => 'Missing order_id or amount.'];
    goto send;
}

// Same as e_fund: webhook is only called on success, so having order_id + amount = success
$isSuccess = (
    $status === '1' || $status === 1 ||
    strtolower((string)$status) === 'success' ||
    strtolower((string)$status) === 'completed' ||
    strtolower((string)$status) === 'successful' ||
    $status === '' || $status === null
);

if ($isSuccess) {
    $ok = completeFundRequestByReference($reference, $amount);
    if ($ok) {
        $__reseller_callback_json = [
            'status' => true,
            'message' => 'NGN ' . number_format($amount, 2) . ' has been successfully added to your wallet.',
            'order_id' => $reference,
            'amount' => $amount,
            'credited' => true,
        ];
    } else {
        $__reseller_callback_json = [
            'status' => true,
            'message' => 'Transaction already confirmed or not found.',
            'order_id' => $reference,
            'amount' => $amount,
            'credited' => false,
        ];
    }
    goto send;
}

$__reseller_callback_json = [
    'status' => true,
    'message' => 'OK',
    'order_id' => $reference,
    'amount' => $amount,
];

send:
$__reseller_callback_body = json_encode($__reseller_callback_json);
if (defined('RESELLER_CALLBACK_VIA_LARAVEL')) {
    return;
}
http_response_code($__reseller_callback_code);
header('Content-Type: application/json; charset=utf-8');
echo $__reseller_callback_body;
exit;
