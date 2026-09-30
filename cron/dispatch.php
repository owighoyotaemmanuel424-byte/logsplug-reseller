<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/config.php';
if(PHP_SAPI!=='cli'||!hash_equals(CRON_SECRET,(string)(getenv('CRON_SECRET')?:''))){exit(1);}
$jobs=['reconcile.php','wallet_reconcile.php','health.php','wallet_sync.php','catalog_sync.php'];
foreach($jobs as $job){$cmd=PHP_BINARY.' '.escapeshellarg(__DIR__.'/'.$job);$out=[];$code=0;exec($cmd.' 2>&1',$out,$code);if($code!==0)error_log('Cron '.$job.' failed: '.implode("\n",$out));}
