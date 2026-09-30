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

        // Build the aggregate service cache that services.php consumes.
        // Each country has its own provider endpoint, so fetch sequentially
        // and throttle between calls to stay below the provider limit.
        $numberResult=$p->allNumberServices($countries['countries']);
        if($numberResult['services']){
            $save('catalog_numbers_services',$numberResult['services']);
        }
        if($numberResult['error']!==''){
            error_log('Logspanel number catalog warnings: '.$numberResult['error']);
        }
    }

    $pause(2);
    $bc=$p->boostCategories();
    if($bc['error']===''){
        $save('catalog_boost_categories',$bc['categories']);
        $pause(2);

        // Fetch the complete boost catalog. If the unfiltered endpoint returns
        // no services, allBoostServices falls back to the documented categories.
        $boostResult=$p->allBoostServices($bc['categories']);
        if($boostResult['services']){
            $save('catalog_boost_services',$boostResult['services']);
        }
        if($boostResult['error']!==''){
            error_log('Logspanel boost catalog warnings: '.$boostResult['error']);
        }
    }
    echo "Catalog sync completed.\n";
} finally {
    $pdo->query("SELECT pg_advisory_unlock(48392019)");
}
