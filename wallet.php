<?php
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/auth_helpers.php';
requireLogin();
$user = getCurrentUser();
$userId = (int) $user['id'];
$balance = getWalletBalance($userId);
$txPage = max(1, (int)($_GET['page'] ?? 1));
$txPerPage = 20;
$txData = getWalletTransactionsPaginated($userId, $txPage, $txPerPage);
$transactions = $txData['items'];
$txTotal = $txData['total'];
$txTotalPages = $txData['total_pages'];
$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : (defined('SITE_TITLE') ? SITE_TITLE : 'Store'));
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : (defined('LOGO_URL') ? trim(LOGO_URL) : '');
$currentUser = $user;
$dbPath = defined('DB_PATH') ? DB_PATH : '';
$sprintPayEnabled = (function_exists('getSetting') && getSetting('sprintpay_enabled') === '1') || (defined('SPRINTPAY_ENABLED') && SPRINTPAY_ENABLED);
$pageTitle = 'Wallet - ' . htmlspecialchars($businessName);
$layout = 'wide';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <h1 class="page-title">My Wallet</h1>
    <div class="card">
        <p class="balance-big">₦<?php echo number_format($balance, 2); ?></p>
        <p class="text-muted mt-1">Current balance</p>
        <?php if ($sprintPayEnabled): ?>
            <a href="fund.php" class="btn btn-primary mt-2">Fund with SprintPay</a>
        <?php else: ?>
            <p class="text-muted mt-2 mb-0">To fund your wallet, contact the store owner. SprintPay can be enabled in the reseller config.</p>
        <?php endif; ?>
    </div>

    <h2 class="section-title">Transactions</h2>
    <div class="card table-card wallet-transactions-card">
        <?php if (empty($transactions)): ?>
            <p class="text-muted mb-0">No transactions yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="wallet-table">
                    <thead>
                        <tr>
                            <th class="wallet-table-date">Date</th>
                            <th class="wallet-table-desc">Description</th>
                            <th class="wallet-table-ref">Reference</th>
                            <th class="wallet-table-amount amount-col">Amount</th>
                            <th class="wallet-table-status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="wallet-table-date"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($tx['date']))); ?></td>
                                <td class="wallet-table-desc"><?php echo htmlspecialchars($tx['description']); ?></td>
                                <td class="wallet-table-ref"><code class="ref-code"><?php echo htmlspecialchars($tx['reference']); ?></code></td>
                                <td class="wallet-table-amount amount-col <?php echo $tx['amount'] >= 0 ? 'credit' : 'debit'; ?>"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>₦<?php echo number_format($tx['amount'], 2); ?></td>
                                <td class="wallet-table-status"><span class="status-badge status-<?php echo $tx['status']; ?>"><?php echo $tx['status'] === 'confirmed' ? 'Confirmed' : 'Pending'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($txTotalPages > 1): ?>
                <nav class="pagination-wrap" aria-label="Transaction pages">
                    <p class="pagination-info text-muted"><?php echo $txTotal; ?> transaction<?php echo $txTotal !== 1 ? 's' : ''; ?> · Page <?php echo $txPage; ?> of <?php echo $txTotalPages; ?></p>
                    <ul class="pagination">
                        <?php if ($txPage > 1): ?>
                            <li><a href="wallet.php?page=<?php echo $txPage - 1; ?>" class="pagination-link" aria-label="Previous">‹ Prev</a></li>
                        <?php endif; ?>
                        <?php
                        $from = max(1, $txPage - 2);
                        $to = min($txTotalPages, $txPage + 2);
                        for ($i = $from; $i <= $to; $i++):
                        ?>
                            <li><a href="wallet.php?page=<?php echo $i; ?>" class="pagination-link <?php echo $i === $txPage ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                        <?php endfor; ?>
                        <?php if ($txPage < $txTotalPages): ?>
                            <li><a href="wallet.php?page=<?php echo $txPage + 1; ?>" class="pagination-link" aria-label="Next">Next ›</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <p class="auth-links">
        <a href="profile.php">Profile</a><span>|</span><a href="index.php">Store</a><span>|</span><a href="logout.php">Logout</a>
    </p>

<?php require __DIR__ . '/includes/footer.php'; ?>
