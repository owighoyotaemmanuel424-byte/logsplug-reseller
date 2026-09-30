<?php
declare(strict_types=1);
require_once __DIR__.'/../admin_helpers.php';
require_once __DIR__.'/includes/audit.php';
require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'||!isset($_POST['order_id'])){header('Location: reported_orders.php');exit;}
$id=(int)$_POST['order_id'];
$st=getDb()->prepare('SELECT id FROM orders WHERE id=? AND reported_at IS NOT NULL LIMIT 1');$st->execute([$id]);
if(!$st->fetchColumn()){header('Location: reported_orders.php?error='.rawurlencode('Order not found or not reported locally.'));exit;}
AuditLog::record('order.report_reviewed','order',(string)$id);
header('Location: reported_orders.php?sent=1');exit;
