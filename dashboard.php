<?php
require_once __DIR__ . '/auth_helpers.php';
requireLogin();

$user = getCurrentUser();
$userId = (int) $user['id'];
$businessName = (function_exists('getSetting') && getSetting('business_name')) ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Store');
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : '';
$balance = getWalletBalance($userId);
$orders = getOrdersByUserPaginated($userId, 1, 5);
$recentOrders = $orders['items'];
$transactions = getWalletTransactionsPaginated($userId, 1, 5)['items'];
$totalOrders = (int) $orders['total'];

$totalSpent = 0.0;
$pdo = getDb();
if ($pdo) {
    try {
        $st = $pdo->prepare('SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=?');
        $st->execute([$userId]);
        $totalSpent = (float) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('Dashboard total spent query failed: '.$e->getMessage());
    }
}

$pageTitle = 'Dashboard - ' . htmlspecialchars($businessName);
$layout = 'wide';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

<main class="customer-dashboard">
    <section class="dashboard-hero">
        <div>
            <span class="dashboard-kicker">CUSTOMER DASHBOARD</span>
            <h1>Welcome back, <?php echo htmlspecialchars((string)($user['name'] ?: 'there')); ?>.</h1>
            <p>Manage your wallet, services and orders from one place.</p>
        </div>
        <a href="index.php#shop" class="dashboard-primary-action">Browse services <span>→</span></a>
    </section>

    <section class="dashboard-stats" aria-label="Account overview">
        <article class="dashboard-stat dashboard-stat-wallet">
            <div class="dashboard-stat-icon">₦</div>
            <div>
                <span>Wallet balance</span>
                <strong>₦<?php echo number_format($balance, 2); ?></strong>
            </div>
            <a href="wallet.php" aria-label="Open wallet">↗</a>
        </article>
        <article class="dashboard-stat">
            <div class="dashboard-stat-icon">↗</div>
            <div>
                <span>Total orders</span>
                <strong><?php echo number_format($totalOrders); ?></strong>
            </div>
            <a href="my_orders.php" aria-label="Open orders">↗</a>
        </article>
        <article class="dashboard-stat">
            <div class="dashboard-stat-icon">₦</div>
            <div>
                <span>Total spent</span>
                <strong>₦<?php echo number_format($totalSpent, 2); ?></strong>
            </div>
            <a href="my_orders.php" aria-label="View spending">↗</a>
        </article>
    </section>

    <section class="dashboard-grid">
        <div class="dashboard-main-column">
            <div class="dashboard-section-head">
                <div>
                    <span class="dashboard-section-kicker">ACTIVITY</span>
                    <h2>Recent orders</h2>
                </div>
                <a href="my_orders.php">View all</a>
            </div>

            <div class="dashboard-card dashboard-orders-card">
                <?php if (empty($recentOrders)): ?>
                    <div class="dashboard-empty">
                        <div class="dashboard-empty-icon">＋</div>
                        <h3>No orders yet</h3>
                        <p>Your completed purchases will appear here.</p>
                        <a href="index.php#shop" class="dashboard-outline-action">Browse services</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-order-list">
                        <?php foreach ($recentOrders as $order): ?>
                            <a class="dashboard-order-row" href="order_details.php?id=<?php echo (int)$order['id']; ?>">
                                <div class="dashboard-order-avatar">S</div>
                                <div class="dashboard-order-info">
                                    <strong><?php echo htmlspecialchars((string)$order['product_name']); ?></strong>
                                    <span>Order #<?php echo (int)$order['id']; ?> · <?php echo htmlspecialchars(date('M j, Y', strtotime((string)$order['created_at']))); ?></span>
                                </div>
                                <div class="dashboard-order-amount">
                                    <strong>₦<?php echo number_format((float)$order['total_amount'], 2); ?></strong>
                                    <?php if (!empty($order['replacement_status']) && $order['replacement_status'] === 'replaced'): ?>
                                        <span class="dashboard-status status-replaced">Replaced</span>
                                    <?php elseif (!empty($order['reported_at'])): ?>
                                        <span class="dashboard-status status-reported">Reported</span>
                                    <?php else: ?>
                                        <span class="dashboard-status status-complete">Completed</span>
                                    <?php endif; ?>
                                </div>
                                <span class="dashboard-row-arrow">›</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <aside class="dashboard-side-column">
            <div class="dashboard-section-head">
                <div>
                    <span class="dashboard-section-kicker">QUICK ACTIONS</span>
                    <h2>Shortcuts</h2>
                </div>
            </div>
            <div class="dashboard-card dashboard-actions-card">
                <a href="fund.php" class="dashboard-action">
                    <span class="dashboard-action-icon">＋</span>
                    <span><strong>Fund wallet</strong><small>Add money to your balance</small></span>
                    <b>›</b>
                </a>
                <a href="index.php#shop" class="dashboard-action">
                    <span class="dashboard-action-icon">◎</span>
                    <span><strong>Buy a service</strong><small>Browse available services</small></span>
                    <b>›</b>
                </a>
                <a href="wallet.php" class="dashboard-action">
                    <span class="dashboard-action-icon">↕</span>
                    <span><strong>Wallet activity</strong><small>Review payments and credits</small></span>
                    <b>›</b>
                </a>
                <a href="profile.php" class="dashboard-action">
                    <span class="dashboard-action-icon">◉</span>
                    <span><strong>Profile & security</strong><small>Manage your account</small></span>
                    <b>›</b>
                </a>
            </div>

            <div class="dashboard-section-head dashboard-activity-head">
                <div>
                    <span class="dashboard-section-kicker">WALLET</span>
                    <h2>Latest activity</h2>
                </div>
                <a href="wallet.php">All</a>
            </div>
            <div class="dashboard-card dashboard-activity-card">
                <?php if (empty($transactions)): ?>
                    <p class="dashboard-muted">No wallet activity yet.</p>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <div class="dashboard-activity-row">
                            <span class="dashboard-activity-icon <?php echo $tx['amount'] >= 0 ? 'is-credit' : 'is-debit'; ?>"><?php echo $tx['amount'] >= 0 ? '↓' : '↑'; ?></span>
                            <span class="dashboard-activity-copy"><strong><?php echo htmlspecialchars((string)$tx['description']); ?></strong><small><?php echo htmlspecialchars(date('M j, g:i A', strtotime((string)$tx['date']))); ?></small></span>
                            <b class="<?php echo $tx['amount'] >= 0 ? 'is-credit-text' : 'is-debit-text'; ?>"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>₦<?php echo number_format(abs((float)$tx['amount']), 2); ?></b>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </section>

    <nav class="dashboard-mobile-nav" aria-label="Customer navigation">
        <a class="is-active" href="dashboard.php"><span>⌂</span>Home</a>
        <a href="index.php#shop"><span>◎</span>Services</a>
        <a href="wallet.php"><span>₦</span>Wallet</a>
        <a href="my_orders.php"><span>↗</span>Orders</a>
        <a href="profile.php"><span>◉</span>Profile</a>
    </nav>
</main>

<style>
.customer-dashboard{padding-bottom:24px}.dashboard-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;margin:-4px 0 28px;padding:34px 36px;border:1px solid #e6e8ef;border-radius:24px;background:linear-gradient(135deg,#111827,#25204d);color:#fff;box-shadow:0 20px 50px rgba(15,23,42,.12)}.dashboard-kicker,.dashboard-section-kicker{font-size:.68rem;font-weight:800;letter-spacing:.15em;color:#a5b4fc}.dashboard-hero h1{margin:8px 0 7px;font-size:clamp(1.8rem,4vw,2.7rem);letter-spacing:-.04em}.dashboard-hero p{margin:0;color:#cbd5e1}.dashboard-primary-action{display:inline-flex;align-items:center;gap:12px;padding:12px 16px;border-radius:12px;background:#fff;color:#312e81;text-decoration:none;font-weight:750;white-space:nowrap}.dashboard-primary-action:hover{transform:translateY(-1px)}.dashboard-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:30px}.dashboard-stat{display:flex;align-items:center;gap:13px;position:relative;padding:19px;background:#fff;border:1px solid #e7e9f0;border-radius:18px;box-shadow:0 8px 25px rgba(15,23,42,.04)}.dashboard-stat-icon{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#eef2ff;color:#4f46e5;font-weight:800}.dashboard-stat span{display:block;color:#667085;font-size:.78rem;margin-bottom:3px}.dashboard-stat strong{display:block;font-size:1.2rem;letter-spacing:-.02em}.dashboard-stat>a{margin-left:auto;color:#98a2b3;text-decoration:none}.dashboard-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.8fr);gap:28px}.dashboard-section-head{display:flex;justify-content:space-between;align-items:end;margin:0 2px 12px}.dashboard-section-head h2{margin:4px 0 0;font-size:1.15rem;letter-spacing:-.02em}.dashboard-section-head>a{font-size:.82rem;color:#4f46e5;font-weight:700;text-decoration:none}.dashboard-card{background:#fff;border:1px solid #e7e9f0;border-radius:18px;box-shadow:0 8px 25px rgba(15,23,42,.04);overflow:hidden}.dashboard-order-row{display:grid;grid-template-columns:42px minmax(0,1fr) auto 18px;gap:13px;align-items:center;padding:16px 18px;border-bottom:1px solid #eef0f4;text-decoration:none;color:inherit}.dashboard-order-row:last-child{border-bottom:0}.dashboard-order-row:hover{background:#fafbff}.dashboard-order-avatar{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#f1f5f9;color:#475467;font-weight:800}.dashboard-order-info{min-width:0}.dashboard-order-info strong{display:block;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dashboard-order-info span{display:block;margin-top:3px;color:#98a2b3;font-size:.75rem}.dashboard-order-amount{text-align:right}.dashboard-order-amount strong{display:block;font-size:.87rem}.dashboard-status{display:inline-block;margin-top:4px;padding:3px 7px;border-radius:999px;font-size:.65rem;font-weight:700}.status-complete{background:#ecfdf3;color:#027a48}.status-reported{background:#fff7ed;color:#c2410c}.status-replaced{background:#eff6ff;color:#1d4ed8}.dashboard-row-arrow{color:#98a2b3;font-size:1.2rem}.dashboard-actions-card{padding:5px 16px}.dashboard-action{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #eef0f4;text-decoration:none;color:inherit}.dashboard-action:last-child{border-bottom:0}.dashboard-action-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:#f4f3ff;color:#4f46e5;font-weight:800;flex:0 0 auto}.dashboard-action>span:nth-child(2){min-width:0;flex:1}.dashboard-action strong{display:block;font-size:.82rem}.dashboard-action small{display:block;color:#98a2b3;font-size:.7rem;margin-top:2px}.dashboard-action>b{color:#98a2b3}.dashboard-activity-head{margin-top:28px}.dashboard-activity-card{padding:7px 16px}.dashboard-activity-row{display:flex;align-items:center;gap:10px;padding:12px 0;border-bottom:1px solid #eef0f4}.dashboard-activity-row:last-child{border-bottom:0}.dashboard-activity-icon{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;font-weight:800}.dashboard-activity-icon.is-credit{background:#ecfdf3;color:#027a48}.dashboard-activity-icon.is-debit{background:#fff1f2;color:#be123c}.dashboard-activity-copy{min-width:0;flex:1}.dashboard-activity-copy strong{display:block;font-size:.72rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dashboard-activity-copy small{display:block;color:#98a2b3;font-size:.66rem;margin-top:2px}.dashboard-activity-row>b{font-size:.7rem;white-space:nowrap}.is-credit-text{color:#027a48}.is-debit-text{color:#be123c}.dashboard-muted{padding:20px;color:#98a2b3;font-size:.85rem}.dashboard-empty{text-align:center;padding:52px 20px}.dashboard-empty-icon{width:46px;height:46px;display:grid;place-items:center;margin:0 auto 12px;border-radius:14px;background:#eef2ff;color:#4f46e5;font-size:1.5rem}.dashboard-empty h3{margin:0 0 5px;font-size:1rem}.dashboard-empty p{margin:0 0 18px;color:#98a2b3;font-size:.8rem}.dashboard-outline-action{display:inline-flex;padding:10px 13px;border:1px solid #d0d5dd;border-radius:10px;color:#344054;text-decoration:none;font-weight:700;font-size:.78rem}.dashboard-mobile-nav{display:none}
@media(max-width:800px){.dashboard-hero{align-items:flex-start;flex-direction:column;padding:26px 22px;border-radius:20px}.dashboard-primary-action{width:100%;justify-content:center}.dashboard-stats{grid-template-columns:1fr;gap:9px}.dashboard-stat{padding:15px}.dashboard-grid{grid-template-columns:1fr;gap:24px}.dashboard-mobile-nav{position:sticky;bottom:10px;z-index:20;display:grid;grid-template-columns:repeat(5,1fr);margin-top:28px;padding:7px;border:1px solid #e4e7ec;border-radius:18px;background:rgba(255,255,255,.94);box-shadow:0 12px 35px rgba(15,23,42,.15);backdrop-filter:blur(14px)}.dashboard-mobile-nav a{display:flex;flex-direction:column;align-items:center;gap:3px;padding:7px 2px;color:#667085;text-decoration:none;font-size:.62rem;font-weight:700}.dashboard-mobile-nav a span{font-size:1rem}.dashboard-mobile-nav a.is-active{color:#4f46e5}.dashboard-order-row{grid-template-columns:36px minmax(0,1fr) auto}.dashboard-order-avatar{width:36px;height:36px}.dashboard-row-arrow{display:none}.dashboard-order-amount strong{font-size:.78rem}}
</style>

<?php require __DIR__ . '/includes/footer.php'; ?>
