<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/db.php';

$pdo=db();
$lock=$pdo->query("SELECT pg_try_advisory_lock(48392019)")->fetchColumn();
if(!$lock){exit("Catalog sync already running.\n");}
try{
    $p=ProviderRegistry::get('logspanel');

    // Provider limit is 60 requests/minute. Keep the sync deliberately conservative
    // and never fan out requests concurrently.
    $pause=function(int $seconds=2):void{ if($seconds>0) sleep($seconds); };
    $save=function(string $key,array $data)use($pdo):void{
        $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')
            ->execute([$key,json_encode(['data'=>$data,'synced_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES)]);
    };

    $items=$p->catalog();
    $save('catalog_logspanel',$items);
    $pause();

    $countries=$p->numberCountries();
    if($countries['error']===''){
        $save('catalog_numbers_countries',$countries['countries']);
        foreach($countries['countries'] as $country){
            // The provider exposes country services individually. Throttle each request.
            $id=(int)($country['id']??0);
            if($id<1) continue;
            $r=$p->numberServices($id);
            if($r['error']!=='') continue;
            $key='catalog_numbers_services_'.$id;
            $save($key,$r['services']);
            $pause(2);
        }
    }

    $pause(2);
    $bc=$p->boostCategories();
    if($bc['error']===''){
        $save('catalog_boost_categories',$bc['categories']);
        $pause(2);
        $bs=$p->boostServices();
        if($bs['error']==='') $save('catalog_boost_services',$bs['services']);
    }
    echo "Catalog sync completed.\n";
} finally {
    $pdo->query("SELECT pg_advisory_unlock(48392019)");
}
