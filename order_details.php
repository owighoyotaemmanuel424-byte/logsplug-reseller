<?php
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';
requireLogin();
$user = getCurrentUser();
$userId = (int) $user['id'];

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($orderId < 1) {
    header('Location: my_orders.php');
    exit;
}
$order = getOrderById($orderId);
if (!$order || (int) $order['user_id'] !== $userId) {
    header('Location: my_orders.php');
    exit;
}

$reportMessage = '';
$reportError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_order'])) {
    $reason = trim($_POST['report_reason'] ?? '');
    $err = reportOrder($orderId, $userId, $reason);
    if ($err === null) {
        header('Location: order_details.php?id=' . $orderId . '&reported=1');
        exit;
    }
    $reportError = $err;
}
if (isset($_GET['reported']) && $_GET['reported'] === '1') {
    $reportMessage = 'Order reported. Admin will review and may replace the item.';
}

function canReportOrder(array $order): bool
{
    if (!empty($order['reported_at'])) {
        return false;
    }
    $created = strtotime($order['created_at']);
    return $created !== false && (time() - $created) <= 2 * 3600;
}

$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Store'));
$pageTitle = 'Order #' . $orderId . ' - ' . htmlspecialchars($businessName);
$layout = 'wide';
$detailsText = isset($order['product_details']) && $order['product_details'] !== '' ? trim($order['product_details']) : '';
$detailsLines = $detailsText !== '' ? array_filter(array_map('trim', explode("\n", $order['product_details'])), function ($line) { return $line !== ''; }) : [];

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>
<div class="site-wrap <?php echo isset($layout) && $layout === 'wide' ? 'wide' : ''; ?>">
    <p class="breadcrumb"><a href="my_orders.php">My Orders</a> → Order #<?php echo (int) $order['id']; ?></p>
    <h1 class="page-title">Order details</h1>

    <?php if ($reportMessage): ?>
        <div class="alert alert-success"><p><?php echo htmlspecialchars($reportMessage); ?></p></div>
    <?php endif; ?>
    <?php if ($reportError): ?>
        <div class="alert alert-error"><p><?php echo htmlspecialchars($reportError); ?></p></div>
    <?php endif; ?>

    <div class="card order-details-card">
        <table class="order-details-table">
            <tr>
                <th>Product</th>
                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
            </tr>
            <tr>
                <th>Date</th>
                <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($order['created_at']))); ?></td>
            </tr>
            <tr>
                <th>Quantity</th>
                <td><?php echo (int) $order['qty']; ?></td>
            </tr>
            <tr>
                <th>Total</th>
                <td>₦<?php echo number_format((float) $order['total_amount'], 2); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php if (!empty($order['replacement_status']) && $order['replacement_status'] === 'replaced'): ?>
                        <span class="status-badge status-replaced">Replaced</span>
                        <?php if (!empty($order['replacement_note'])): ?>
                            <span class="order-replacement-note" title="<?php echo htmlspecialchars($order['replacement_note']); ?>"><?php echo htmlspecialchars(strlen($order['replacement_note']) > 60 ? substr($order['replacement_note'], 0, 60) . '…' : $order['replacement_note']); ?></span>
                        <?php endif; ?>
                    <?php elseif (!empty($order['reported_at'])): ?>
                        <span class="status-badge status-reported">Reported</span>
                    <?php else: ?>
                        <span class="status-badge status-ok">Completed</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div class="order-details-section">
            <h2 class="order-details-section-title">Product details (copy)</h2>
            <?php if (!empty($detailsLines)): ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle order-details-copy-table">
                        <thead class="table-light">
                            <tr>
                                <th>Product Details</th>
                                <th class="text-center">Copy</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailsLines as $idx => $line): ?>
                                <tr>
                                    <td>
                                        <input type="text" readonly class="form-control border-0 bg-light small copy-input" value="<?php echo htmlspecialchars($line); ?>" id="copyInput<?php echo (int) $orderId . '_' . (int) $idx; ?>">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle copy-btn" data-target="copyInput<?php echo (int) $orderId . '_' . (int) $idx; ?>" aria-label="Copy to clipboard">
                                            <i class="fa fa-copy copy-icon-visible"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No product details stored for this order.</p>
            <?php endif; ?>
        </div>

        <div class="order-details-section order-details-report-section">
            <h2 class="order-details-section-title">Report order</h2>
            <?php if (canReportOrder($order)): ?>
                <p class="text-muted mb-2">If there is an issue with this order (e.g. invalid or missing product), you can report it within 2 hours of purchase. Admin will review and may replace the item.</p>
                <button type="button" class="btn btn-secondary" id="openReportModalBtn">Report this order</button>
            <?php elseif (!empty($order['reported_at'])): ?>
                <p class="text-muted">You have already reported this order. We will review and may replace the item.</p>
            <?php else: ?>
                <p class="text-muted">Reporting is only allowed within 2 hours of purchase.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report modal -->
    <div id="reportModal" class="modal" role="dialog" aria-labelledby="reportModalTitle" aria-hidden="true" hidden>
        <div class="modal-backdrop" id="reportModalBackdrop"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="reportModalTitle">Report order</h3>
                <button type="button" class="modal-close" id="reportModalClose" aria-label="Close">&times;</button>
            </div>
            <form method="post" action="order_details.php?id=<?php echo (int) $orderId; ?>">
                <input type="hidden" name="report_order" value="1">
                <div class="modal-body">
                    <p class="text-muted">Please enter the reason for reporting this order (optional). Admin will review and may replace the item.</p>
                    <label for="reportReason" class="sr-only">Reason</label>
                    <textarea id="reportReason" name="report_reason" class="order-details-textarea" rows="3" placeholder="e.g. Invalid or missing product..." maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="reportModalCancel">Cancel</button>
                    <button type="submit" class="btn btn-secondary">Submit report</button>
                </div>
            </form>
        </div>
    </div>

    <p class="auth-links">
        <a href="my_orders.php">← Back to My Orders</a><span>|</span><a href="profile.php">Profile</a><span>|</span><a href="wallet.php">Wallet</a><span>|</span><a href="index.php">Store</a><span>|</span><a href="logout.php">Logout</a>
    </p>
</div>

<script>
(function() {
    var modal = document.getElementById('reportModal');
    var openBtn = document.getElementById('openReportModalBtn');
    var closeBtn = document.getElementById('reportModalClose');
    var cancelBtn = document.getElementById('reportModalCancel');
    var backdrop = document.getElementById('reportModalBackdrop');
    if (!modal || !openBtn) return;
    function openModal() {
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('reportReason').focus();
    }
    function closeModal() {
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
    }
    openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    var copyBtns = document.querySelectorAll('.copy-btn');
    copyBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = btn.getAttribute('data-target');
            var el = id ? document.getElementById(id) : null;
            if (el) {
                el.select();
                el.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    var oldTitle = btn.getAttribute('title');
                    btn.setAttribute('title', 'Copied!');
                    setTimeout(function() { btn.setAttribute('title', oldTitle || 'Copy'); }, 2000);
                } catch (e) {
                    btn.setAttribute('title', 'Copy failed');
                    setTimeout(function() { btn.removeAttribute('title'); }, 2000);
                }
            }
        });
    });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
