<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/db.php';
$p=ProviderRegistry::get('logspanel');$items=$p->catalog();
db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')
 ->execute(['catalog_logspanel',json_encode(['items'=>$items,'synced_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES)]);
