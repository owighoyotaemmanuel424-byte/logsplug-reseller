<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/db.php';
db()->prepare("DELETE FROM audit_logs WHERE created_at < CURRENT_TIMESTAMP - INTERVAL '365 days'")->execute();
db()->prepare("DELETE FROM schema_migrations WHERE applied_at < CURRENT_TIMESTAMP - INTERVAL '5 years'")->execute();
