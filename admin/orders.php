<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

$pdo = getDb();
$orders = [];
if ($pdo) {
    $st = $pdo->query('
        SELECT o.id, o.user_id, o.product_id, o.product_name, o.qty, o.unit_price, o.total_amount, o.api_order_id, o.created_at,
               u.email AS user_email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.created_at DESC
        LIMIT 500
    ');
    if ($st) {
        $orders = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

$adminPageTitle = 'Orders';
$currentAdminPage = 'orders';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <div class="admin-card">
        <h2 class="admin-card-title">Order history (<?php echo count($orders); ?>)</h2>
        <p class="admin-card-desc">All orders placed by your downstream customers.</p>
        <?php if (empty($orders)): ?>
            <p class="text-muted">No orders yet. Orders are recorded when customers place an order on the store.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th>Customer</th>
                            <th>API Order ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?php echo (int)$o['id']; ?></td>
                            <td><?php echo htmlspecialchars($o['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($o['product_name']); ?></td>
                            <td><?php echo (int)$o['qty']; ?></td>
                            <td class="admin-amount">₦<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                            <td><?php echo $o['user_id'] ? htmlspecialchars($o['user_email'] ?? '#' . $o['user_id']) : '—'; ?></td>
                            <td><?php echo htmlspecialchars($o['api_order_id'] ?? '—'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
