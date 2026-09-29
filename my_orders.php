<?php
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';
requireLogin();
$user = getCurrentUser();
$userId = (int) $user['id'];

$orderPage = max(1, (int)($_GET['page'] ?? 1));
$orderPerPage = 15;
$ordersData = getOrdersByUserPaginated($userId, $orderPage, $orderPerPage);
$orders = $ordersData['items'];
$ordersTotal = $ordersData['total'];
$ordersTotalPages = $ordersData['total_pages'];

$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Store'));
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$currentUser = $user;
$dbPath = defined('DB_PATH') ? DB_PATH : '';

$pageTitle = 'My Orders - ' . htmlspecialchars($businessName);
$layout = 'wide';

$orderSuccessMessage = '';
if (isset($_GET['ordered']) && $_GET['ordered'] === '1') {
    $orderSuccessMessage = 'Order successful. Your order has been recorded.';
}

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">My Orders</h1>

    <?php if ($orderSuccessMessage): ?>
        <div class="alert alert-success"><p><?php echo htmlspecialchars($orderSuccessMessage); ?></p></div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="card orders-empty-card">
            <p class="text-muted mb-1">You have no orders yet.</p>
            <p class="mb-0"><a href="index.php" class="btn btn-primary">Browse products</a></p>
        </div>
    <?php else: ?>
        <div class="card table-card orders-card">
            <div class="table-responsive">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th class="orders-table-date">Date</th>
                            <th class="orders-table-product">Product</th>
                            <th class="orders-table-qty">Qty</th>
                            <th class="orders-table-total">Total</th>
                            <th class="orders-table-status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td class="orders-table-date"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($o['created_at']))); ?></td>
                            <td class="orders-table-product"><a href="order_details.php?id=<?php echo (int) $o['id']; ?>" class="order-product-link"><?php echo htmlspecialchars($o['product_name']); ?></a></td>
                            <td class="orders-table-qty"><?php echo (int) $o['qty']; ?></td>
                            <td class="orders-table-total">₦<?php echo number_format((float) $o['total_amount'], 2); ?></td>
                            <td class="orders-table-status">
                                <?php if (!empty($o['replacement_status']) && $o['replacement_status'] === 'replaced'): ?>
                                    <span class="status-badge status-replaced">Replaced</span>
                                    <?php if (!empty($o['replacement_note'])): ?>
                                        <span class="order-replacement-note" title="<?php echo htmlspecialchars($o['replacement_note']); ?>"><?php echo htmlspecialchars(strlen($o['replacement_note']) > 40 ? substr($o['replacement_note'], 0, 40) . '…' : $o['replacement_note']); ?></span>
                                    <?php endif; ?>
                                <?php elseif (!empty($o['reported_at'])): ?>
                                    <span class="status-badge status-reported">Reported</span>
                                <?php else: ?>
                                    <span class="status-badge status-ok">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($ordersTotalPages > 1): ?>
                <nav class="pagination-wrap" aria-label="Order pages">
                    <p class="pagination-info text-muted"><?php echo $ordersTotal; ?> order<?php echo $ordersTotal !== 1 ? 's' : ''; ?> · Page <?php echo $orderPage; ?> of <?php echo $ordersTotalPages; ?></p>
                    <ul class="pagination">
                        <?php if ($orderPage > 1): ?>
                            <li><a href="my_orders.php?page=<?php echo $orderPage - 1; ?>" class="pagination-link" aria-label="Previous">‹ Prev</a></li>
                        <?php endif; ?>
                        <?php
                        $from = max(1, $orderPage - 2);
                        $to = min($ordersTotalPages, $orderPage + 2);
                        for ($i = $from; $i <= $to; $i++):
                        ?>
                            <li><a href="my_orders.php?page=<?php echo $i; ?>" class="pagination-link <?php echo $i === $orderPage ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($orderPage < $ordersTotalPages): ?>
                            <li><a href="my_orders.php?page=<?php echo $orderPage + 1; ?>" class="pagination-link" aria-label="Next">Next ›</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="auth-links">
        <a href="profile.php">Profile</a><span>|</span><a href="wallet.php">Wallet</a><span>|</span><a href="index.php">Store</a><span>|</span><a href="logout.php">Logout</a>
    </p>

<?php require __DIR__ . '/includes/footer.php'; ?>
