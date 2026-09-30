<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/providers/ProviderRegistry.php';
require_once __DIR__.'/../includes/db.php';

$pdo=db();
$lock=$pdo->query("SELECT pg_try_advisory_lock(48392019)")->fetchColumn();
if(!$lock){exit("Catalog sync already running.\n");}

try{
    $p=ProviderRegistry::get('logspanel');

    // Logspanel limits API traffic. Run one catalog section at a time and
    // never let a failed section prevent the remaining catalogs from syncing.
    $pause=function(int $seconds=2):void{ if($seconds>0) sleep($seconds); };
    $save=function(string $key,array $data)use($pdo):void{
        $pdo->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value')
            ->execute([$key,json_encode(['data'=>$data,'synced_at'=>gmdate('c')],JSON_UNESCAPED_SLASHES)]);
    };
    $loadCached=function(string $key)use($pdo):array{
        $st=$pdo->prepare('SELECT value FROM settings WHERE key=?');
        $st->execute([$key]);
        $raw=$st->fetchColumn();
        $d=is_string($raw)?json_decode($raw,true):null;
        return is_array($d['data']??null)?$d['data']:[];
    };

    // Do not refetch the 932+ item logs catalog every 15-minute dispatcher
    // cycle. A failed/rate-limited logs refresh must never block numbers or
    // boosting from being populated.
    $existingLogs=$loadCached('catalog_logspanel');
    if(!$existingLogs){
        try{
            $items=$p->catalog();
            if($items) $save('catalog_logspanel',$items);
        }catch(Throwable $e){
            error_log('Logspanel logs catalog sync warning: '.$e->getMessage());
        }
    }

    // Numbers: countries -> services for every country, stored as one
    // aggregate cache consumed by services.php. Do not save a partial catalog.
    try{
        $countries=$p->numberCountries();
        if($countries['error']===''){
            $save('catalog_numbers_countries',$countries['countries']);
            $pause();

            $numberResult=$p->allNumberServices($countries['countries']);
            if($numberResult['error']==='' && $numberResult['services']){
                $save('catalog_numbers_services',$numberResult['services']);
            }elseif($numberResult['error']!==''){
                error_log('Logspanel number catalog sync warning: '.$numberResult['error']);
            }
        }else{
            error_log('Logspanel number countries sync warning: '.$countries['error']);
        }
    }catch(Throwable $e){
        error_log('Logspanel number catalog sync warning: '.$e->getMessage());
    }

    $pause();

    // Boosting: fetch categories, then the complete service set. If the
    // unfiltered endpoint returns no services, the provider adapter falls
    // back to category-specific requests.
    try{
        $bc=$p->boostCategories();
        if($bc['error']===''){
            $save('catalog_boost_categories',$bc['categories']);
            $pause();

            $boostResult=$p->allBoostServices($bc['categories']);
            if($boostResult['error']==='' && $boostResult['services']){
                $save('catalog_boost_services',$boostResult['services']);
            }elseif($boostResult['error']!==''){
                error_log('Logspanel boost catalog sync warning: '.$boostResult['error']);
            }
        }else{
            error_log('Logspanel boost categories sync warning: '.$bc['error']);
        }
    }catch(Throwable $e){
        error_log('Logspanel boost catalog sync warning: '.$e->getMessage());
    }

    echo "Catalog sync completed.\n";
}finally{
    $pdo->query("SELECT pg_advisory_unlock(48392019)");
}
