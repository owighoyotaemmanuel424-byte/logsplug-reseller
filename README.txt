RESELLER MINI-SITE
==================

1. Configure the required environment variables (see config.sample.php).
2. For local development, config.php reads the same environment variables:
   - RESELLER_API_KEY: Your private API key (set only on the server)
   - API_BASE_URL: The platform URL (e.g. https://loggsplug.online) with no trailing slash
   - MARKUP_PERCENT: Your selling margin (e.g. 10 for 10%)
   - SITE_TITLE / BUSINESS_NAME: Your store name
   - LOGO_URL: Optional URL or path to your logo image
   - For end-user wallet funding: set SPRINTPAY_* keys if you use SprintPay (see below).

CALLBACK URL (SprintPay / e_fund)
- When this folder is your document root (e.g. php -S localhost:9091 -t reseller-site), use:
  https://your-domain/fund_callback.php   (no "reseller-site" in path)
- When the main Laravel app serves the callback (same domain as main site), use:
  https://your-domain/reseller-site/fund_callback
3. Upload this folder to your server (PHP with curl, PDO SQLite enabled).
   Required structure: keep the includes/ folder and its files (head.php, header.php, footer.php).
   If you see "Failed to open stream ... includes/head.php", the includes/ directory was not uploaded or is in the wrong place.
4. Never commit real API keys or payment credentials. Keep production secrets in the hosting provider environment settings.

SETTLEMENT ACCOUNT
- Set your settlement account in the main platform Reseller dashboard (or via API PUT /api/reseller/settlement-account).
- End-of-day sales will be settled to that account.

ADMIN PANEL
- Visit /admin/ (or admin/ on your server). Requires DB_PATH to be set.
- First visit: set an admin password (min 8 characters). You log in as Admin.
- Admin: can set markup %, upload logo, manage site/SprintPay, set optional Reseller password, and see markup change requests on the dashboard.
- Reseller: if Admin sets a "Reseller password", that user can log in to admin with read-only markup (view only). Reseller can request a markup change via "Request markup"; Admin sees requests on the dashboard and can update markup in Settings.
- Site & SprintPay: edit site title, business name, upload logo or set logo URL, extra amount; Admin only can set markup %. Upload logo saves to uploads/ in the reseller-site folder.
- Users: list registered users with email, name, wallet balance, join date.
- Orders: list order history (recorded when customers place orders on the store).
- Settings stored in the SQLite DB override config.php. API key and API_BASE_URL stay in config.php only.

END-USER FEATURES (optional)
- Your customers can Register, Login, view Profile and Wallet.
- They can fund their wallet via SprintPay (configure in Admin or config.php and your callback URL).
- Purchases can deduct from their local wallet; the mini-site calls the platform Reseller API to place orders.

API ENDPOINTS USED
- GET {API_BASE_URL}/api/reseller/products (header: X-Api-Key)
- POST {API_BASE_URL}/api/reseller/order (header: X-Api-Key, body: product_id, qty)
- PUT {API_BASE_URL}/api/reseller/settlement-account (header: X-Api-Key, body: settlement_account)
