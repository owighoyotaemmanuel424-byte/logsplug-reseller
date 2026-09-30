<?php
declare(strict_types=1);
require_once __DIR__.'/includes/db.php';

if (PHP_SAPI !== 'cli') {
    return;
}

try {
    $pdo=db_direct();
    // Bootstrap the migration ledger before querying it. The first migration also
    // defines this table, but the runner must be able to check it before 000_core.sql.
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec('SELECT pg_advisory_lock(48392017)');
    try {
        $files=glob(__DIR__.'/db/migrations/*.sql') ?: [];
        natsort($files);
        foreach($files as $file){
            $version=basename($file);
            $check=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE version=?');
            $check->execute([$version]);
            if($check->fetchColumn()) continue;
            $sql=(string)file_get_contents($file);
            $pdo->beginTransaction();
            try{
                $pdo->exec($sql);
                $pdo->prepare('INSERT INTO schema_migrations(version) VALUES(?)')->execute([$version]);
                $pdo->commit();
            }catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                throw new RuntimeException('Migration '.$version.' failed.',0,$e);
            }
        }
    } finally { $pdo->exec('SELECT pg_advisory_unlock(48392017)'); }
} catch(Throwable $e) {
    error_log('Migration runner failed: '.$e->getMessage());
    exit(1);
}
