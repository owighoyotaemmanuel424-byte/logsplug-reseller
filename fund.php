<?php
/**
 * Fund wallet via SprintPay. Configure in Admin → Site & SprintPay (or config.php).
 */
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';
requireLogin();
$user = getCurrentUser();
$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Store'));
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$currentUser = $user;
$dbPath = defined('DB_PATH') ? DB_PATH : '';
$enabled = (function_exists('getSetting') && getSetting('sprintpay_enabled') === '1') || (defined('SPRINTPAY_ENABLED') && SPRINTPAY_ENABLED);
$callbackUrl = (function_exists('getSetting') && getSetting('sprintpay_callback_url')) ? trim((string)getSetting('sprintpay_callback_url')) : (defined('SPRINTPAY_CALLBACK_URL') ? trim(SPRINTPAY_CALLBACK_URL) : '');
$sprintpayKey = (function_exists('getSetting') && getSetting('sprintpay_merchant_id')) ? trim((string)getSetting('sprintpay_merchant_id')) : (defined('SPRINTPAY_MERCHANT_ID') ? trim(SPRINTPAY_MERCHANT_ID) : (defined('SPRINTPAY_KEY') ? trim(SPRINTPAY_KEY) : ''));
$paymentUrl = function_exists('getSetting') ? (getSetting('sprintpay_payment_url') ?: '') : '';

$message = '';
$messageType = '';
$fundReference = null;

if (!$enabled) {
    header('Location: wallet.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float) ($_POST['amount'] ?? 0);
    if ($amount < 1) {
        $message = 'Enter a valid amount (at least 1).';
        $messageType = 'error';
    } elseif ($callbackUrl === '') {
        $message = 'SprintPay callback URL is not configured. Contact the store owner.';
        $messageType = 'error';
    } elseif ($sprintpayKey === '') {
        $message = 'SprintPay key / Merchant ID is not configured. Contact the store owner.';
        $messageType = 'error';
    } else {
        $ref = createFundRequest((int)$user['id'], $amount);
        if ($ref === null) {
            $message = 'Could not create funding request. Try again.';
            $messageType = 'error';
        } else {
            $fundReference = $ref;
            $amountRounded = round($amount, 2);
            $userEmail = isset($user['email']) ? $user['email'] : '';
            if ($paymentUrl !== '') {
                $redirect = str_replace(
                    ['{ref}', '{reference}', '{amount}', '{callback}'],
                    [urlencode($ref), urlencode($ref), urlencode((string)$amountRounded), urlencode($callbackUrl)],
                    $paymentUrl
                );
                if (strpos($redirect, 'ref=') === false) {
                    $sep = strpos($redirect, '?') !== false ? '&' : '?';
                    $redirect .= $sep . 'ref=' . urlencode($ref) . '&amount=' . urlencode((string)$amountRounded);
                }
                header('Location: ' . $redirect);
                exit;
            }
            $sprintPayPayUrl = 'https://web.sprintpay.online/pay';
            $redirect = $sprintPayPayUrl . '?amount=' . urlencode((string)$amountRounded) . '&key=' . urlencode($sprintpayKey) . '&ref=' . urlencode($ref) . '&email=' . urlencode($userEmail);
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$pageTitle = 'Fund Wallet - ' . htmlspecialchars($businessName);
$layout = 'narrow';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">Fund Wallet (SprintPay)</h1>
    <div class="auth-card">
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        <?php if (!$fundReference): ?>
        <form method="post">
            <div class="form-group">
                <label for="amount">Amount (₦)</label>
                <input type="number" id="amount" name="amount" step="0.01" min="1" placeholder="0.00" required>
            </div>
            <button type="submit" class="btn btn-primary">Continue to SprintPay</button>
        </form>
        <?php endif; ?>
        <p class="auth-links">
            <a href="wallet.php">Back to Wallet</a>
        </p>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
