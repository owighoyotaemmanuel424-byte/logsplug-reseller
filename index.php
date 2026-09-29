<?php
/**
 * Reseller Mini-Site - Product list and order via API
 * Requires config.php (copy from config.sample.php).
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!file_exists(__DIR__ . '/config.php')) {
    die('Please copy config.sample.php to config.php and set your API key and API_BASE_URL.');
}
require_once __DIR__ . '/config.php';

// Ensure includes directory and required files exist (prevents "Failed to open stream" on deploy)
$includesDir = __DIR__ . '/includes';
$requiredIncludes = ['head.php', 'header.php', 'footer.php'];
foreach ($requiredIncludes as $file) {
    if (!is_file($includesDir . '/' . $file)) {
        header('Content-Type: text/plain; charset=utf-8');
        die('Missing file: includes/' . $file . '. Upload the full reseller-site folder including the includes/ directory (head.php, header.php, footer.php). Path checked: ' . $includesDir . '/' . $file);
    }
}
$dbPath = defined('DB_PATH') ? DB_PATH : '';
if ($dbPath !== '') {
    require_once __DIR__ . '/init_db.php';
    require_once __DIR__ . '/auth_helpers.php';
}
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;

$apiKey = defined('RESELLER_API_KEY') ? RESELLER_API_KEY : '';
$baseUrl = rtrim(defined('API_BASE_URL') ? API_BASE_URL : '', '/');
$markup = (float)(function_exists('getSetting') && getSetting('markup_percent') !== null ? getSetting('markup_percent') : (defined('MARKUP_PERCENT') ? MARKUP_PERCENT : 0));
$adminExtra = (float)(function_exists('getSetting') && getSetting('admin_extra_amount') !== null ? getSetting('admin_extra_amount') : 0);
$siteTitle = (function_exists('getSetting') && getSetting('site_title') !== null && getSetting('site_title') !== '') ? getSetting('site_title') : (defined('SITE_TITLE') ? SITE_TITLE : 'Reseller Store');
$businessName = (function_exists('getSetting') && getSetting('business_name') !== null && getSetting('business_name') !== '') ? getSetting('business_name') : (defined('BUSINESS_NAME') ? BUSINESS_NAME : $siteTitle);
$logoUrl = (function_exists('getSetting') && getSetting('logo_url') !== null) ? trim((string)getSetting('logo_url')) : (defined('LOGO_URL') ? trim(LOGO_URL) : '');

$pageTitle = $businessName;

$products = [];
$error = '';
$orderMessage = '';
if (isset($_GET['ordered']) && $_GET['ordered'] === '1') {
    $orderMessage = 'Order successful. Your order has been recorded.';
}

// Fetch products from API
if ($apiKey && $baseUrl) {
    $ch = curl_init($baseUrl . '/api/reseller/products');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $apiKey],
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $res = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($code === 200 && $res) {
        $data = json_decode($res, true);
        if (!empty($data['success']) && isset($data['data'])) {
            $products = $data['data'];
        } else {
            $error = $data['message'] ?? 'Failed to load products.';
        }
    } else {
        if ($res) {
            $data = is_string($res) ? json_decode($res, true) : null;
            if (is_array($data) && isset($data['message']) && (string) $data['message'] !== '') {
                $error = $data['message'];
            } else {
                $error = 'HTTP ' . $code . '. Check API key and reseller status on the platform. If the key is correct, the key may be revoked or the reseller suspended – check Admin → Resellers on ' . parse_url($baseUrl, PHP_URL_HOST) . '.';
            }
        } else {
            $error = $curlError ? 'Connection error: ' . $curlError : 'Could not reach ' . $baseUrl . '. Check API_BASE_URL and server connectivity.';
        }
    }
}

// Handle order form submit (only authenticated users when DB/auth is enabled)
$canOrder = ($dbPath === '' || $currentUser !== null);
$orderSuccessRedirect = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    if (!$canOrder) {
        header('Location: login.php?redirect=' . urlencode('index.php'));
        exit;
    }
    $productId = (int) $_POST['product_id'];
    $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 1;
    $qty = max(1, min(10000, $qty));

    // Find product and calculate total cost (user pays this from wallet when logged in)
    $sellPrice = 0;
    $productName = 'Product #' . $productId;
    foreach ($products as $p) {
        if ((int) $p['id'] === $productId) {
            $productName = $p['name'];
            $sellPrice = round($p['reseller_price'] * (1 + $markup / 100) + $adminExtra, 2);
            break;
        }
    }
    $orderTotal = round($sellPrice * $qty, 2);

    $apiPayload = [
        'product_id' => $productId,
        'qty' => $qty,
        'api_key' => $apiKey,
    ];

    // When user is logged in, check wallet balance before calling reseller API
    if ($currentUser && $dbPath !== '' && function_exists('getWalletBalance')) {
        $userBalance = getWalletBalance((int) $currentUser['id']);
        if ($userBalance < $orderTotal) {
            $orderMessage = 'Insufficient balance. You have ₦' . number_format($userBalance, 2) . '. This order costs ₦' . number_format($orderTotal, 2) . '. Please fund your wallet (Wallet page).';
        } else {
            $ch = curl_init($baseUrl . '/api/reseller/order');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($apiPayload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'X-Api-Key: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $orderData = $res ? json_decode($res, true) : [];
            if ($code === 200 && !empty($orderData['success'])) {
                if (function_exists('deductWalletBalance') && deductWalletBalance((int) $currentUser['id'], $orderTotal)) {
                    if (function_exists('recordOrder')) {
                        $details = '';
                        if (!empty($orderData['delivered']) && is_array($orderData['delivered'])) {
                            $parts = [];
                            foreach ($orderData['delivered'] as $d) {
                                $parts[] = isset($d['details']) ? trim((string) $d['details']) : '';
                            }
                            $details = implode("\n", array_filter($parts));
                        }
                        recordOrder((int) $currentUser['id'], $productId, $productName, $qty, $sellPrice, (string) ($orderData['order_id'] ?? ''), $details);
                    }
                    $orderSuccessRedirect = true;
                } else {
                    $orderMessage = 'Order placed with platform but wallet deduction failed. Please contact support.';
                }
            } else {
                $orderMessage = 'Order failed: ' . ($orderData['message'] ?? 'Unknown error');
            }
        }
    } else {
        $ch = curl_init($baseUrl . '/api/reseller/order');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($apiPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'X-Api-Key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $orderData = $res ? json_decode($res, true) : [];
        if ($code === 200 && !empty($orderData['success'])) {
            if (function_exists('recordOrder')) {
                $details = '';
                if (!empty($orderData['delivered']) && is_array($orderData['delivered'])) {
                    $parts = [];
                    foreach ($orderData['delivered'] as $d) {
                        $parts[] = isset($d['details']) ? trim((string) $d['details']) : '';
                    }
                    $details = implode("\n", array_filter($parts));
                }
                recordOrder($currentUser ? (int) $currentUser['id'] : null, $productId, $productName, $qty, $sellPrice, (string) ($orderData['order_id'] ?? ''), $details);
            }
            $orderSuccessRedirect = true;
        } else {
            $orderMessage = 'Order failed: ' . ($orderData['message'] ?? 'Unknown error');
        }
    }

    if ($orderSuccessRedirect && $dbPath !== '' && $currentUser) {
        header('Location: my_orders.php?ordered=1');
        exit;
    }
    if ($orderSuccessRedirect && ($dbPath === '' || !$currentUser)) {
        header('Location: index.php?ordered=1');
        exit;
    }
}

$bodyClass = 'page-reseller-index';
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>

    <?php if ($error): ?>
        <div class="alert alert-error"><p><?php echo htmlspecialchars($error); ?></p></div>
    <?php endif; ?>
    <?php if ($orderMessage): ?>
        <div class="alert <?php echo (strpos($orderMessage, 'failed') !== false || strpos($orderMessage, 'Insufficient') !== false) ? 'alert-error' : 'alert-success'; ?>"><p><?php echo htmlspecialchars($orderMessage); ?><?php if (strpos($orderMessage, 'success') !== false): ?> <a href="my_orders.php">View My Orders</a><?php endif; ?></p></div>
    <?php endif; ?>

    <?php if ($dbPath !== '' && !$currentUser): ?>
        <div class="reseller-auth-prompt">
            <a href="login.php">Login</a> or <a href="register.php">Register</a> to manage your wallet and orders.
        </div>
    <?php endif; ?>

    <h1 class="page-title">Products</h1>
    <?php if (empty($products)): ?>
        <div class="card">
            <p class="text-muted mb-0">No products available.</p>
        </div>
    <?php else:
        $categories = [];
        foreach ($products as $p) {
            $cat = isset($p['category']) && (string)$p['category'] !== '' ? $p['category'] : 'Uncategorized';
            if (!isset($categories[$cat])) $categories[$cat] = [];
            $p['amount'] = round($p['reseller_price'] * (1 + $markup / 100) + $adminExtra, 2);
            $p['image_url'] = $p['image_url'] ?? '';
            $categories[$cat][] = $p;
        }
        ksort($categories);
        $initialCount = 5;
    ?>
        <div class="reseller-toolbar">
            <div class="reseller-search-wrap">
                <input type="search" id="product-search" class="reseller-search" placeholder="Search products..." aria-label="Search products">
            </div>
            <div class="reseller-category-dropdown-wrap">
                <label for="shop-by-category" class="reseller-category-label">Shop by category</label>
                <select id="shop-by-category" class="reseller-category-select" aria-label="Filter by category">
                    <option value="*">All categories</option>
                    <?php foreach (array_keys($categories) as $catName): ?>
                        <option value="<?php echo htmlspecialchars($catName); ?>"><?php echo htmlspecialchars($catName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php foreach ($categories as $catName => $catProducts):
            $totalInCategory = count($catProducts);
            $hasMoreInCategory = $totalInCategory > $initialCount;
            $moreCount = $hasMoreInCategory ? ($totalInCategory - $initialCount) : 0;
        ?>
        <section class="reseller-category-section" data-category="<?php echo htmlspecialchars($catName); ?>">
            <h2 class="reseller-category-heading"><?php echo htmlspecialchars($catName); ?></h2>
            <div class="reseller-product-grid">
                <?php foreach ($catProducts as $i => $p):
                    $isMore = $i >= $initialCount;
                    $imgUrl = $p['image_url'];
                ?>
                <div class="reseller-product-card product-card" data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>" data-category="<?php echo htmlspecialchars($catName); ?>" <?php echo $isMore ? ' style="display:none;" data-more="1"' : ''; ?>>
                    <div class="reseller-product-card__img-wrap">
                        <?php if ($imgUrl): ?>
                            <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="reseller-product-card__img" loading="lazy" referrerpolicy="no-referrer" decoding="async" onerror="this.onerror=null;this.style.display='none';var f=this.nextElementSibling;if(f)f.classList.add('reseller-product-card__img-placeholder--show');">
                            <div class="reseller-product-card__img-placeholder reseller-product-card__img-placeholder--fallback" aria-hidden="true">No image</div>
                        <?php else: ?>
                            <div class="reseller-product-card__img-placeholder" aria-hidden="true">No image</div>
                        <?php endif; ?>
                    </div>
                    <div class="reseller-product-card__body">
                        <h3 class="reseller-product-card__title"><?php echo htmlspecialchars($p['name']); ?></h3>
                        <div class="reseller-product-card__meta">
                            <span class="card__pill reseller-product-card__pill reseller-product-card__pill--amount">₦<?php echo number_format($p['amount'], 2); ?></span>
                            <span class="card__pill reseller-product-card__pill reseller-product-card__pill--stock">Stock: <?php echo (int)$p['in_stock']; ?></span>
                        </div>
                        <?php if ($canOrder): ?>
                        <form method="post" class="reseller-product-card__form">
                            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                            <input type="number" name="qty" class="qty-input" value="1" min="1" max="<?php echo max(1, (int)$p['in_stock']); ?>">
                            <button type="submit" class="btn btn-primary btn-cart" title="Add to cart" aria-label="Add to cart">
                                <svg class="btn-cart-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            </button>
                        </form>
                        <?php else: ?>
                        <a href="login.php?redirect=<?php echo urlencode('index.php'); ?>" class="reseller-product-card__login-btn" title="Login to order" aria-label="Login to order">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($hasMoreInCategory): ?>
                <div class="reseller-view-more-wrap">
                    <button type="button" class="btn btn-secondary reseller-view-more" data-category="<?php echo htmlspecialchars($catName); ?>">View more (<?php echo $moreCount; ?> more)</button>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <script>
        (function() {
            var search = document.getElementById('product-search');
            var categorySelect = document.getElementById('shop-by-category');
            var sections = document.querySelectorAll('.reseller-category-section');
            var currentCategory = '*';

            function filter() {
                var q = (search && search.value) ? search.value.trim().toLowerCase() : '';
                sections.forEach(function(section) {
                    var cat = section.getAttribute('data-category');
                    var showSection = (currentCategory === '*' || cat === currentCategory);
                    var cards = section.querySelectorAll('.product-card');
                    var visible = 0;
                    cards.forEach(function(card) {
                        var name = card.getAttribute('data-name') || '';
                        var matchSearch = !q || name.indexOf(q) !== -1;
                        var show = showSection && matchSearch;
                        card.style.display = show ? '' : 'none';
                        if (show) visible++;
                    });
                    section.style.display = visible ? '' : 'none';
                });
            }

            if (search) {
                search.addEventListener('input', filter);
                search.addEventListener('search', filter);
            }

            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    currentCategory = categorySelect.value || '*';
                    filter();
                });
            }

            document.querySelectorAll('.reseller-view-more').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var cat = btn.getAttribute('data-category');
                    var section = document.querySelector('.reseller-category-section[data-category="' + cat.replace(/"/g, '\\"') + '"]');
                    if (!section) return;
                    var moreCards = section.querySelectorAll('.product-card[data-more="1"]');
                    moreCards.forEach(function(c) { c.style.display = ''; c.removeAttribute('data-more'); });
                    btn.parentElement.style.display = 'none';
                });
            });
        })();
        </script>
    <?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
