<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

$message = '';
$messageType = '';
$isAdmin = isAdminRole();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    if ($section === 'site') {
        setSetting('site_title', trim($_POST['site_title'] ?? ''));
        setSetting('business_name', trim($_POST['business_name'] ?? ''));
        setSetting('telegram_url', trim($_POST['telegram_url'] ?? ''));
        setSetting('whatsapp_url', trim($_POST['whatsapp_url'] ?? ''));
        if ($isAdmin) {
            setSetting('markup_percent', (string)(floatval($_POST['markup_percent'] ?? 0)));
        }
        setSetting('admin_extra_amount', (string)(floatval($_POST['admin_extra_amount'] ?? 0)));
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);
            if (in_array($mime, $allowed, true)) {
                $uploadDir = dirname(__DIR__) . '/uploads';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION) ?: 'png';
                $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'png';
                $dest = $uploadDir . '/logo.' . strtolower($ext);
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                    setSetting('logo_url', 'uploads/logo.' . strtolower($ext));
                }
            }
        } elseif (trim($_POST['logo_url'] ?? '') !== '') {
            setSetting('logo_url', trim($_POST['logo_url'] ?? ''));
        }
        $message = 'Site settings saved.';
        $messageType = 'success';
    } elseif ($section === 'sprintpay') {
        setSetting('sprintpay_enabled', isset($_POST['sprintpay_enabled']) ? '1' : '0');
        setSetting('sprintpay_merchant_id', trim($_POST['sprintpay_merchant_id'] ?? ''));
        setSetting('sprintpay_callback_url', trim($_POST['sprintpay_callback_url'] ?? ''));
        setSetting('sprintpay_payment_url', trim($_POST['sprintpay_payment_url'] ?? ''));
        $message = 'SprintPay settings saved.';
        $messageType = 'success';
    } elseif ($section === 'request_markup' && !$isAdmin) {
        $requested = (float)($_POST['requested_percent'] ?? 0);
        $note = trim($_POST['request_note'] ?? '');
        if ($requested >= 0 && $requested <= 100) {
            saveMarkupRequest($requested, $note);
            $message = 'Markup request sent. Admin will review it.';
            $messageType = 'success';
        } else {
            $message = 'Enter a valid markup % (0–100).';
            $messageType = 'error';
        }
    } elseif ($section === 'admin_password' && $isAdmin) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';
        $hash = getSetting('admin_password_hash');
        if (!$hash || !password_verify($current, $hash)) {
            $message = 'Current password is wrong.';
            $messageType = 'error';
        } elseif (strlen($new) < 8) {
            $message = 'New password must be at least 8 characters.';
            $messageType = 'error';
        } elseif ($new !== $confirm) {
            $message = 'New passwords do not match.';
            $messageType = 'error';
        } else {
            setSetting('admin_password_hash', password_hash($new, PASSWORD_DEFAULT));
            $message = 'Admin password updated.';
            $messageType = 'success';
        }
    } elseif ($section === 'reseller_password' && $isAdmin) {
        $new = $_POST['reseller_password'] ?? '';
        $confirm = $_POST['reseller_password_confirm'] ?? '';
        if ($new === '' && $confirm === '') {
            setSetting('reseller_password_hash', '');
            $message = 'Reseller password removed.';
            $messageType = 'success';
        } elseif (strlen($new) < 8) {
            $message = 'Reseller password must be at least 8 characters.';
            $messageType = 'error';
        } elseif ($new !== $confirm) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            setSetting('reseller_password_hash', password_hash($new, PASSWORD_DEFAULT));
            $message = 'Reseller password set.';
            $messageType = 'success';
        }
    }
}

$siteTitle = getSetting('site_title') ?: (defined('SITE_TITLE') ? SITE_TITLE : '');
$businessName = getSetting('business_name') ?: (defined('BUSINESS_NAME') ? BUSINESS_NAME : '');
$logoUrl = getSetting('logo_url') !== null ? getSetting('logo_url') : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$markupPercent = getSetting('markup_percent') !== null ? getSetting('markup_percent') : (defined('MARKUP_PERCENT') ? (string)MARKUP_PERCENT : '10');
$adminExtraAmount = getSetting('admin_extra_amount') !== null ? getSetting('admin_extra_amount') : '0';
$telegramUrl = getSetting('telegram_url') ?: '';
$whatsappUrl = getSetting('whatsapp_url') ?: '';
$sprintpayEnabled = (getSetting('sprintpay_enabled') ?: (defined('SPRINTPAY_ENABLED') && SPRINTPAY_ENABLED ? '1' : '0')) === '1';
$sprintpayMerchantId = getSetting('sprintpay_merchant_id') ?: (defined('SPRINTPAY_MERCHANT_ID') ? SPRINTPAY_MERCHANT_ID : '');
$sprintpayCallbackUrl = getSetting('sprintpay_callback_url') ?: (defined('SPRINTPAY_CALLBACK_URL') ? SPRINTPAY_CALLBACK_URL : '');
$sprintpayPaymentUrl = getSetting('sprintpay_payment_url') ?: '';

$adminPageTitle = 'Site & SprintPay';
$currentAdminPage = 'settings';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <?php if ($message): ?>
        <div class="alert <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>"><p><?php echo htmlspecialchars($message); ?></p></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2 style="margin-top:0;">Site settings</h2>
        <p class="text-muted">Override config.php. API key and base URL stay in config.php.</p>
        <form method="post" class="admin-form" enctype="multipart/form-data">
            <input type="hidden" name="section" value="site">
            <div class="form-group">
                <label for="site_title">Site title</label>
                <input type="text" id="site_title" name="site_title" value="<?php echo htmlspecialchars($siteTitle); ?>">
            </div>
            <div class="form-group">
                <label for="business_name">Business name (header)</label>
                <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($businessName); ?>">
            </div>
            <div class="form-group">
                <label for="logo_upload">Upload logo</label>
                <input type="file" id="logo_upload" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                <p class="help">Or enter a URL below. Upload replaces URL.</p>
            </div>
            <div class="form-group">
                <label for="logo_url">Logo URL (if not uploading)</label>
                <input type="text" id="logo_url" name="logo_url" value="<?php echo htmlspecialchars($logoUrl); ?>" placeholder="https://... or uploads/logo.png">
            </div>
            <div class="form-group">
                <label for="markup_percent">Markup %</label>
                <?php if ($isAdmin): ?>
                    <input type="number" id="markup_percent" name="markup_percent" step="0.5" min="0" value="<?php echo htmlspecialchars($markupPercent); ?>">
                    <p class="help">Your selling margin on top of API reseller cost. Only admin can change this.</p>
                <?php else: ?>
                    <p><strong><?php echo htmlspecialchars($markupPercent); ?>%</strong> (view only – only admin can change; use "Request markup" below to ask for a change)</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="admin_extra_amount">Extra amount per item</label>
                <input type="number" id="admin_extra_amount" name="admin_extra_amount" step="0.01" min="0" value="<?php echo htmlspecialchars($adminExtraAmount); ?>">
                <p class="help">Added to each item (API price + markup).</p>
            </div>
            <div class="form-group">
                <label for="telegram_url">Telegram support link</label>
                <input type="url" id="telegram_url" name="telegram_url" value="<?php echo htmlspecialchars($telegramUrl); ?>" placeholder="https://t.me/yourusername">
                <p class="help">Shows a floating Telegram icon on your reseller site when set.</p>
            </div>
            <div class="form-group">
                <label for="whatsapp_url">WhatsApp support link</label>
                <input type="url" id="whatsapp_url" name="whatsapp_url" value="<?php echo htmlspecialchars($whatsappUrl); ?>" placeholder="https://wa.me/234...">
                <p class="help">Shows a floating WhatsApp icon on your reseller site when set.</p>
            </div>
            <button type="submit" class="btn btn-primary">Save site settings</button>
        </form>
    </div>

    <?php if (!$isAdmin): ?>
    <div class="admin-card" id="request-markup">
        <h2 style="margin-top:0;">Request markup change</h2>
        <p class="text-muted">You cannot set markup. Submit a request for admin to review.</p>
        <form method="post" class="admin-form">
            <input type="hidden" name="section" value="request_markup">
            <div class="form-group">
                <label for="requested_percent">Requested markup %</label>
                <input type="number" id="requested_percent" name="requested_percent" step="0.5" min="0" max="100" value="<?php echo htmlspecialchars($markupPercent); ?>" required>
            </div>
            <div class="form-group">
                <label for="request_note">Note (optional)</label>
                <input type="text" id="request_note" name="request_note" placeholder="e.g. Reason for change">
            </div>
            <button type="submit" class="btn btn-primary">Send request to admin</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <h2 style="margin-top:0;">SprintPay (wallet funding)</h2>
        <p class="text-muted">Enable so customers can fund their wallet.</p>
        <form method="post" class="admin-form">
            <input type="hidden" name="section" value="sprintpay">
            <div class="form-group">
                <label><input type="checkbox" name="sprintpay_enabled" value="1" <?php echo $sprintpayEnabled ? 'checked' : ''; ?>> Enable SprintPay</label>
            </div>
            <div class="form-group">
                <label for="sprintpay_merchant_id">Merchant ID</label>
                <input type="text" id="sprintpay_merchant_id" name="sprintpay_merchant_id" value="<?php echo htmlspecialchars($sprintpayMerchantId); ?>">
            </div>
            <div class="form-group">
                <label for="sprintpay_callback_url">Callback URL</label>
                <input type="text" id="sprintpay_callback_url" name="sprintpay_callback_url" value="<?php echo htmlspecialchars($sprintpayCallbackUrl); ?>" placeholder="https://yoursite.com/fund_callback.php">
                <p class="help">Full URL to fund_callback.php (SprintPay POSTs here after payment).</p>
            </div>
            <div class="form-group">
                <label for="sprintpay_payment_url">Payment / checkout URL (optional)</label>
                <input type="text" id="sprintpay_payment_url" name="sprintpay_payment_url" value="<?php echo htmlspecialchars($sprintpayPaymentUrl); ?>" placeholder="https://sprintpay.com/pay?ref={ref}&amount={amount}">
                <p class="help">If set, user is redirected here after entering amount. Use {ref}, {amount}, {callback} as placeholders.</p>
            </div>
            <button type="submit" class="btn btn-primary">Save SprintPay settings</button>
        </form>
    </div>

    <?php if ($isAdmin): ?>
    <div class="admin-card">
        <h2 style="margin-top:0;">Change admin password</h2>
        <form method="post" class="admin-form">
            <input type="hidden" name="section" value="admin_password">
            <div class="form-group">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="new_password">New password (min 8)</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="new_password_confirm">Confirm new password</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" required>
            </div>
            <button type="submit" class="btn btn-primary">Update password</button>
        </form>
    </div>

    <div class="admin-card">
        <h2 style="margin-top:0;">Reseller password (optional)</h2>
        <p class="text-muted">If set, reseller can log in with this password and see admin (read-only markup, can request markup change).</p>
        <form method="post" class="admin-form">
            <input type="hidden" name="section" value="reseller_password">
            <div class="form-group">
                <label for="reseller_password">New reseller password</label>
                <input type="password" id="reseller_password" name="reseller_password" minlength="8" placeholder="Leave blank to remove">
            </div>
            <div class="form-group">
                <label for="reseller_password_confirm">Confirm</label>
                <input type="password" id="reseller_password_confirm" name="reseller_password_confirm" minlength="8">
            </div>
            <button type="submit" class="btn btn-primary">Set reseller password</button>
        </form>
    </div>
    <?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
