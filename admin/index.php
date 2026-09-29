<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();
$stats = getAdminStats();
$resellerBalance = getResellerPlatformBalance();
$markupRequests = isAdminRole() ? getMarkupRequests() : [];
$adminPageTitle = 'Dashboard';
$currentAdminPage = 'dashboard';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <?php if (isAdminRole()): ?>
    <p class="text-muted" style="margin-bottom:16px;">Logged in as <strong>Admin</strong>. You can set markup and manage reseller requests.</p>
    <?php else: ?>
    <p class="text-muted" style="margin-bottom:16px;">Logged in as <strong>Reseller</strong>. Markup is view-only; use "Request markup" to ask admin for a change.</p>
    <?php endif; ?>

    <div class="admin-stats">
        <div class="admin-stat admin-stat-balance">
            <div class="admin-stat-value"><?php echo $resellerBalance !== null ? '₦' . number_format($resellerBalance, 2) : '—'; ?></div>
            <div class="admin-stat-label">Reseller balance (platform)</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-value"><?php echo $stats['users']; ?></div>
            <div class="admin-stat-label">Users</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-value"><?php echo $stats['orders']; ?></div>
            <div class="admin-stat-label">Total orders</div>
        </div>
        <div class="admin-stat">
            <div class="admin-stat-value"><?php echo $stats['orders_today']; ?></div>
            <div class="admin-stat-label">Orders today</div>
        </div>
    </div>

    <?php if (isAdminRole() && !empty($markupRequests)): ?>
    <div class="admin-card">
        <h2 style="margin-top:0;">Markup change requests</h2>
        <p class="text-muted">Reseller requested markup changes. Update markup in <a href="settings.php">Settings</a> if you approve.</p>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Requested %</th>
                    <th>Note</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($markupRequests as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars(number_format((float)$r['requested_percent'], 1)); ?>%</td>
                    <td><?php echo htmlspecialchars($r['note'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!isAdminRole()): ?>
    <div class="admin-card">
        <h2 style="margin-top:0;">Request markup change</h2>
        <p>You cannot set markup. Request a change and admin will review it.</p>
        <p><a href="settings.php#request-markup" class="btn btn-primary">Request markup</a></p>
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <h2 style="margin-top:0;">Quick links</h2>
        <p><a href="settings.php">Site &amp; SprintPay settings</a> – Update store name, logo<?php echo isAdminRole() ? ', markup' : ''; ?>, and wallet funding.</p>
        <p><a href="users.php">Customers &amp; wallets</a> – View downstream customers, wallet balances, fund a wallet or delete a customer.</p>
        <p><a href="funding.php">Funding</a> – View all wallet funding (SprintPay and other).</p>
        <p><a href="orders.php">Orders</a> – View order history.</p>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
