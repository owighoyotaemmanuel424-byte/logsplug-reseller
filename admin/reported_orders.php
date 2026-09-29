<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_order'], $_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];
    $note = trim($_POST['replacement_note'] ?? '');
    if (setOrderReplaced($orderId, $note)) {
        $message = 'Order marked as replaced. The customer will see it on My Orders.';
        $messageType = 'success';
    } else {
        $message = 'Failed to update order.';
        $messageType = 'error';
    }
}

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $message = 'Report sent to main site. Platform admin can now replace the product.';
    $messageType = 'success';
}
if (isset($_GET['error'])) {
    $message = $_GET['error'];
    $messageType = 'error';
}

$reported = getReportedOrders();
$adminPageTitle = 'Reported orders';
$currentAdminPage = 'reported';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <?php if ($message): ?>
        <div class="admin-alert <?php echo $messageType === 'error' ? 'admin-alert-error' : 'admin-alert-success'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2 class="admin-card-title">Reported orders (<?php echo count($reported); ?>)</h2>
        <p class="admin-card-desc">Customers can report an order within 2 hours of purchase. Send the report to the main site so the platform can replace the product; then you can mark as replaced for the customer.</p>
        <?php if (empty($reported)): ?>
            <p class="text-muted">No reported orders.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Report reason</th>
                            <th>Reported at</th>
                            <th>Status</th>
                            <th>Report to main site</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reported as $o): ?>
                        <tr>
                            <td><?php echo (int)$o['id']; ?></td>
                            <td><?php echo htmlspecialchars($o['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($o['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($o['email'] ?? '—'); ?> (<?php echo htmlspecialchars($o['name'] ?? '—'); ?>)</td>
                            <td><?php echo htmlspecialchars($o['report_reason'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($o['reported_at'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($o['replacement_status']) && $o['replacement_status'] === 'replaced'): ?>
                                    <strong>Replaced</strong>
                                    <?php if (!empty($o['replacement_note'])): ?>
                                        <br><small><?php echo htmlspecialchars($o['replacement_note']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($o['api_order_id'])): ?>
                                <form method="post" action="report_to_platform.php" class="admin-form-inline">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Report to main site</button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($o['replacement_status']) || $o['replacement_status'] !== 'replaced'): ?>
                                <form method="post" class="admin-form-inline" style="display:inline;">
                                    <input type="hidden" name="replace_order" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                    <input type="text" name="replacement_note" placeholder="Replacement note" class="admin-inline-input" style="width:140px; padding:6px 8px; margin-right:6px;">
                                    <button type="submit" class="btn btn-primary btn-sm">Mark replaced</button>
                                </form>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
