<?php
require_once __DIR__ . '/../admin_helpers.php'; requireAdmin();
$adminPageTitle='Provider Control'; $currentAdminPage='providers'; $provider=getResellerProviderStatus(); $balance=$provider['balance'];
require __DIR__.'/includes/head.php'; require __DIR__.'/includes/header.php'; ?>
<div class="master-dashboard">
<div class="master-hero"><div><span class="master-kicker">PROVIDER OPERATIONS</span><h1>Provider control</h1><p>Configure the Logspanel API without editing Render environment variables.</p></div><div class="master-hero-actions"><a class="btn btn-secondary" href="settings.php">Site settings</a><a class="btn btn-secondary" href="../services.php" target="_blank" rel="noopener">View catalog</a></div></div>

<div class="master-stats">
<div class="master-stat master-stat-primary"><span class="master-stat-icon">◎</span><div><span>Provider balance</span><strong><?=$balance!==null?'₦'.number_format($balance,2):'—'?></strong></div><small><?=$provider['connected']?'Connected':'Not connected'?></small></div>
<div class="master-stat"><span class="master-stat-icon">▤</span><div><span>Provider</span><strong>Logspanel</strong></div><small>API v1</small></div>
</div>

<div class="admin-card" style="margin-top:16px;">
<h2 class="admin-card-title">Logspanel API</h2>
<p style="margin-top:0;color:#667085;">The API key is stored server-side. It is never displayed after saving.</p>
<form method="post" action="provider_save.php" autocomplete="off">
<label for="api_base_url">API base URL</label>
<input id="api_base_url" name="api_base_url" type="url" value="<?=htmlspecialchars((string)API_BASE_URL)?>" required>
<label for="api_key" style="margin-top:12px;">Logspanel API key</label>
<input id="api_key" name="api_key" type="password" placeholder="<?=RESELLER_API_KEY !== '' ? 'Saved — enter a new key to replace it' : 'Paste your Logspanel API key'}" autocomplete="new-password">
<label style="display:flex;gap:8px;align-items:center;margin-top:12px;"><input type="checkbox" name="clear_key" value="1"> Remove saved API key</label>
<button class="btn btn-primary" type="submit" style="margin-top:16px;">Save provider settings</button>
</form>
</div>

<?php if (!$provider['connected']): ?><div class="alert alert-error" style="margin-top:16px;"><strong>Connection:</strong> <?=htmlspecialchars($provider['message'])?></div><?php else: ?><div class="alert alert-success" style="margin-top:16px;"><strong>Connection:</strong> Logspanel API is responding.</div><?php endif; ?>

<div class="admin-card" style="margin-top:16px;"><h2 class="admin-card-title">Provider controls</h2><div class="master-actions"><a href="../services.php" target="_blank" rel="noopener">Service catalog ↗</a><a href="orders.php">Order monitoring</a><a href="reported_orders.php">Provider issues</a></div></div>
</div>
<?php require __DIR__.'/includes/footer.php'; ?>