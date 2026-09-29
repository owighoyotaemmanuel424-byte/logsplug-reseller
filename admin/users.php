<?php
require_once __DIR__ . '/../admin_helpers.php';
requireAdmin();

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'fund' && isset($_POST['user_id'], $_POST['amount'])) {
            $userId = (int) $_POST['user_id'];
            $amount = (float) $_POST['amount'];
            if ($amount > 0 && $userId > 0) {
                if (adminCreditWallet($userId, $amount)) {
                    header('Location: users.php?funded=1&user_id=' . $userId);
                    exit;
                }
                $error = 'Failed to credit wallet.';
            } else {
                $error = 'Invalid amount or user.';
            }
        }
    }
}
if (isset($_GET['funded']) && $_GET['funded'] === '1') {
    $message = 'Wallet funded successfully.';
}
if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $message = 'Customer deleted.';
}

$pdo = getDb();
$users = [];
if ($pdo) {
    $st = $pdo->query('
        SELECT u.id, u.email, u.name, u.created_at, COALESCE(w.balance, 0) AS balance
        FROM users u
        LEFT JOIN wallets w ON w.user_id = u.id
        ORDER BY u.created_at DESC
    ');
    if ($st) {
        $users = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

$adminPageTitle = 'Customers & wallets';
$currentAdminPage = 'users';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <?php if ($message): ?>
        <div class="admin-alert admin-alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="admin-alert admin-alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2 class="admin-card-title">Customers (<?php echo count($users); ?>)</h2>
        <p class="admin-card-desc">Downstream customers and their wallet balances. You can fund a wallet or remove a customer.</p>
        <?php if (empty($users)): ?>
            <p class="text-muted">No customers yet.</p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Wallet balance</th>
                            <th>Joined</th>
                            <th class="admin-th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo (int) $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><span class="admin-user-email"><?php echo htmlspecialchars($u['email']); ?></span></td>
                            <td class="admin-amount">₦<?php echo number_format((float) $u['balance'], 2); ?></td>
                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                            <td class="admin-actions-cell">
                                <div class="admin-actions">
                                    <button type="button" class="btn btn-primary btn-sm admin-btn-fund" data-user-id="<?php echo (int) $u['id']; ?>" data-user-name="<?php echo htmlspecialchars($u['name']); ?>" data-user-email="<?php echo htmlspecialchars($u['email']); ?>">Fund wallet</button>
                                    <form method="post" action="delete_user.php" class="admin-form-inline" onsubmit="return confirm('Permanently delete this customer? Their orders will be kept but unlinked. This cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Fund wallet modal -->
    <div id="fundWalletModal" class="admin-modal" role="dialog" aria-labelledby="fundModalTitle" hidden>
        <div class="admin-modal-backdrop" id="fundModalBackdrop"></div>
        <div class="admin-modal-dialog">
            <div class="admin-modal-content">
                <div class="admin-modal-header">
                    <h3 id="fundModalTitle">Fund wallet</h3>
                    <button type="button" class="admin-modal-close" id="fundModalClose" aria-label="Close">&times;</button>
                </div>
                <form method="post" action="users.php">
                    <input type="hidden" name="action" value="fund">
                    <input type="hidden" name="user_id" id="fundUserId" value="">
                    <div class="admin-modal-body">
                        <p class="admin-modal-user-info text-muted" id="fundUserInfo">—</p>
                        <div class="admin-form form-group">
                            <label for="fundAmount">Amount (₦)</label>
                            <input type="number" name="amount" id="fundAmount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="admin-modal-footer">
                        <button type="button" class="btn btn-outline-secondary" id="fundModalCancel">Cancel</button>
                        <button type="submit" class="btn btn-primary">Credit wallet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var modal = document.getElementById('fundWalletModal');
        var openBtns = document.querySelectorAll('.admin-btn-fund');
        var closeBtn = document.getElementById('fundModalClose');
        var cancelBtn = document.getElementById('fundModalCancel');
        var backdrop = document.getElementById('fundModalBackdrop');
        var userIdInput = document.getElementById('fundUserId');
        var userInfoEl = document.getElementById('fundUserInfo');
        var amountInput = document.getElementById('fundAmount');
        function openModal(userId, userName, userEmail) {
            if (userIdInput) userIdInput.value = userId || '';
            if (userInfoEl) userInfoEl.textContent = (userName || '') + ' (' + (userEmail || '') + ')';
            if (amountInput) amountInput.value = '';
            if (modal) { modal.removeAttribute('hidden'); amountInput.focus(); }
        }
        function closeModal() {
            if (modal) modal.setAttribute('hidden', '');
        }
        openBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                openModal(this.getAttribute('data-user-id'), this.getAttribute('data-user-name'), this.getAttribute('data-user-email'));
            });
        });
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (backdrop) backdrop.addEventListener('click', closeModal);
    })();
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
