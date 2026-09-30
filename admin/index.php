<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();
$stats=getAdminStats();
$resellerBalance=getResellerPlatformBalance();
$markupRequests=isAdminRole()?getMarkupRequests():[];
$recentOrders=[];$recentFunding=[];
try{$pdo=getDb();if($pdo){
$st=$pdo->query("SELECT o.id,o.product_name,o.total_amount,o.status,o.created_at,u.email AS user_email FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 8");if($st)$recentOrders=$st->fetchAll(PDO::FETCH_ASSOC);
$st=$pdo->query("SELECT f.id,f.amount,f.reference,f.status,f.created_at,u.email AS user_email FROM fund_requests f LEFT JOIN users u ON u.id=f.user_id ORDER BY f.created_at DESC LIMIT 8");if($st)$recentFunding=$st->fetchAll(PDO::FETCH_ASSOC);
}}catch(Throwable $e){error_log("Admin dashboard read warning: ".$e->getMessage());}
$adminPageTitle='Master Super Admin Dashboard';$currentAdminPage='dashboard';
require __DIR__.'/includes/head.php';require __DIR__.'/includes/header.php';
?>
<div class="master-dashboard">
<div class="master-hero"><div><span class="master-kicker">COMMAND CENTER</span><h1>Master Super Admin</h1><p>Central control for customers, wallets, orders, funding, provider connectivity and platform operations.</p></div><div class="master-hero-actions"><a class="btn btn-primary" href="users.php">Manage customers</a><a class="btn btn-secondary" href="settings.php">Platform settings</a></div></div>
<?php if(!isAdminRole()): ?><div class="admin-alert admin-alert-error">This account has reseller-level access. Sensitive master controls are restricted to the administrator role.</div><?php endif; ?>
<div class="master-section-heading"><div><span class="master-kicker">OVERVIEW</span><h2>Platform at a glance</h2></div><span class="master-live-pill"><i></i> Live data</span></div>
<div class="master-stats">
<div class="master-stat master-stat-primary"><span class="master-stat-icon">₦</span><div><span>Provider balance</span><strong><?php echo $resellerBalance!==null?'₦'.number_format($resellerBalance,2):'—';?></strong></div><small><?php echo $resellerBalance!==null?'Provider API connected':'Provider balance unavailable';?></small></div>
<div class="master-stat"><span class="master-stat-icon">♙</span><div><span>Customers</span><strong><?php echo number_format($stats['users']);?></strong></div><a href="users.php">Open customers →</a></div>
<div class="master-stat"><span class="master-stat-icon">▤</span><div><span>All orders</span><strong><?php echo number_format($stats['orders']);?></strong></div><a href="orders.php">Open orders →</a></div>
<div class="master-stat"><span class="master-stat-icon">↗</span><div><span>Orders today</span><strong><?php echo number_format($stats['orders_today']);?></strong></div><a href="orders.php">View activity →</a></div>
</div>
<div class="master-grid">
<div class="admin-card master-control-card"><h2>Customer control</h2><p>Accounts, wallets and customer operations.</p><div class="master-actions"><a href="users.php">Customer directory</a><a href="users.php">Wallet balances</a><a href="users.php">Fund a wallet</a></div></div>
<div class="admin-card master-control-card"><h2>Financial operations</h2><p>Monitor funding and wallet activity.</p><div class="master-actions"><a href="funding.php">Funding queue</a><a href="funding.php">Funding history</a><a href="settings.php">Funding settings</a></div></div>
<div class="admin-card master-control-card"><h2>Order operations</h2><p>Review platform order activity.</p><div class="master-actions"><a href="orders.php">Order management</a><a href="reported_orders.php">Reported orders</a><a href="../services.php" target="_blank" rel="noopener">Service catalog ↗</a></div></div>
<div class="admin-card master-control-card"><h2>Platform control</h2><p>Configure the reseller storefront.</p><div class="master-actions"><a href="settings.php">Site settings</a><a href="settings.php">Pricing &amp; markup</a><a href="settings.php">Provider configuration</a></div></div>
</div>
<div class="master-lower-grid">
<div class="admin-card"><h2>System health</h2><div class="master-health-list"><div class="master-health-row"><span>Database</span><strong class="is-ok"><i></i>Connected</strong></div><div class="master-health-row"><span>Provider API</span><strong class="<?php echo $resellerBalance!==null?'is-ok':'is-warn';?>"><i></i><?php echo $resellerBalance!==null?'Connected':'Unavailable';?></strong></div><div class="master-health-row"><span>PHP runtime</span><strong class="is-ok"><i></i><?php echo htmlspecialchars(PHP_VERSION);?></strong></div></div></div>
<div class="admin-card"><h2>Security &amp; administration</h2><p>Restricted administrative entry points remain protected by the existing administrator session and recovery flow.</p><a class="btn btn-secondary" href="<?php echo isAdminRole()?'master-login.php':'login.php';?>"><?php echo isAdminRole()?'Master authentication':'Administrator login';?></a></div>
</div>
<?php if(isAdminRole()&&!empty($markupRequests)): ?><div class="admin-card"><h2>Pending markup requests</h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Requested</th><th>Note</th><th>Date</th></tr></thead><tbody><?php foreach($markupRequests as $r): ?><tr><td><?php echo htmlspecialchars(number_format((float)$r['requested_percent'],1));?>%</td><td><?php echo htmlspecialchars($r['note']??'—');?></td><td><?php echo htmlspecialchars($r['created_at']);?></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
<div class="master-lower-grid">
<div class="admin-card"><h2>Recent orders <a class="master-card-link" href="orders.php">View all</a></h2><?php if($recentOrders): ?><div class="master-activity-list"><?php foreach($recentOrders as $o): ?><div class="master-activity-row"><div><strong>#<?php echo (int)$o['id'];?> · <?php echo htmlspecialchars($o['product_name']??'Order');?></strong><span><?php echo htmlspecialchars($o['user_email']??'Guest');?></span></div><div><strong>₦<?php echo number_format((float)($o['total_amount']??0),2);?></strong><span><?php echo htmlspecialchars($o['status']??'—');?></span></div></div><?php endforeach;?></div><?php else:?><p class="text-muted">No orders recorded yet.</p><?php endif;?></div>
<div class="admin-card"><h2>Recent funding <a class="master-card-link" href="funding.php">View all</a></h2><?php if($recentFunding): ?><div class="master-activity-list"><?php foreach($recentFunding as $f): ?><div class="master-activity-row"><div><strong>#<?php echo (int)$f['id'];?> · <?php echo htmlspecialchars($f['user_email']??'Customer');?></strong><span><?php echo htmlspecialchars($f['reference']??'No reference');?></span></div><div><strong>₦<?php echo number_format((float)($f['amount']??0),2);?></strong><span><?php echo htmlspecialchars($f['status']??'—');?></span></div></div><?php endforeach;?></div><?php else:?><p class="text-muted">No funding records yet.</p><?php endif;?></div>
</div></div>
<?php require __DIR__.'/includes/footer.php'; ?>