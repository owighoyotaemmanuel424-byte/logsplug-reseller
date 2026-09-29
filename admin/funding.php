<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

$funding = getFundRequestsAll();
$adminPageTitle = 'Wallet funding';
$currentAdminPage = 'funding';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <div class="admin-card">
        <h2 class="admin-card-title">Wallet funding (<?php echo count($funding); ?>)</h2>
        <p class="admin-card-desc">All SprintPay and other wallet funding requests from your customers.</p>
        <?php if (empty($funding)): ?>
            <p class="text-muted">No funding records yet.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($funding as $f): ?>
                        <tr>
                            <td><?php echo (int) $f['id']; ?></td>
                            <td>
                                <span class="admin-user-email"><?php echo htmlspecialchars($f['user_email'] ?? '—'); ?></span>
                                <?php if (!empty($f['user_name'])): ?>
                                    <br><span class="admin-user-name text-muted"><?php echo htmlspecialchars($f['user_name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-amount">₦<?php echo number_format((float) $f['amount'], 2); ?></td>
                            <td><code class="admin-ref"><?php echo htmlspecialchars($f['reference'] ?? '—'); ?></code></td>
                            <td>
                                <?php
                                $status = $f['status'] ?? 'pending';
                                $class = $status === 'completed' ? 'admin-badge admin-badge-success' : 'admin-badge admin-badge-warning';
                                ?>
                                <span class="<?php echo $class; ?>"><?php echo htmlspecialchars($status); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($f['created_at'] ?? '—'); ?></td>
                            <td><?php echo !empty($f['completed_at']) ? htmlspecialchars($f['completed_at']) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
