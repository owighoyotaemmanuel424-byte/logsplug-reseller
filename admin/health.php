<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/../includes/providers/ProviderHealth.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');echo json_encode(ProviderHealth::all(),JSON_UNESCAPED_SLASHES);