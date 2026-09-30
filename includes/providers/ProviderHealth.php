<?php
declare(strict_types=1);
require_once __DIR__.'/ProviderRegistry.php';
final class ProviderHealth {
 public static function all():array{
  $out=[];foreach(ProviderRegistry::ids() as $id){try{$out[$id]=ProviderRegistry::get($id)->health();}catch(Throwable $e){$out[$id]=['ok'=>false,'status'=>0,'message'=>'Unavailable'];}}return $out;
 }
}
