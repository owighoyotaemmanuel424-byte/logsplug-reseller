<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/db.php';
final class AuditLog {
 public static function record(string $action,?string $entityType=null,?string $entityId=null,array $metadata=[]):void{
  $actor=isset($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null;
  db()->prepare('INSERT INTO audit_logs(actor_admin_id,action,entity_type,entity_id,metadata) VALUES(?,?,?,?,?::jsonb)')
    ->execute([$actor,$action,$entityType,$entityId,json_encode($metadata,JSON_UNESCAPED_SLASHES)]);
 }
}
