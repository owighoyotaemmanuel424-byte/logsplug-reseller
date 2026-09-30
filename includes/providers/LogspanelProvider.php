<?php
declare(strict_types=1);
require_once __DIR__.'/ProviderInterface.php';
require_once __DIR__.'/../naira.php';
require_once __DIR__.'/ProviderSchema.php';
require_once __DIR__.'/../http.php';

final class LogspanelProvider implements ProviderInterface
{
    public function __construct(private array $config, private Closure $secretResolver) {}

    private function base(): string {
        $base=rtrim(trim((string)($this->config['base_url']??'https://logspanel.com/api/v1')),'/');
        // Accept only the API origin/base. If an old admin setting accidentally
        // contains an endpoint, strip it so requests never become /api/v1/https://...
        $parsed=parse_url($base);
        if(!is_array($parsed)||empty($parsed['scheme'])||empty($parsed['host'])){
            $base='https://logspanel.com/api/v1';
        }
        $base=rtrim($base,'/');
        $marker='/api/v1';
        $pos=strpos($base,$marker);
        if($pos!==false) $base=substr($base,0,$pos+strlen($marker));
        return rtrim($base,'/');
    }
    private function request(string $method,string $path,?array $payload=null,?string $idempotency=null): array
    {
        $key=($this->secretResolver)('api_key');
        if(!$key) return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Provider credential is not configured.'];
        $headers=['Accept: application/json','Authorization: Bearer '.$key,'Content-Type: application/json','User-Agent: LogsPlug-Reseller/2.0'];
        if($idempotency)$headers[]='Idempotency-Key: '.$idempotency;
        // Pagination links from Logspanel may be absolute URLs. Never prepend the API base to one.
        $url=preg_match('#^https?://#i',trim($path)) ? trim($path) : $this->base().'/'.ltrim($path,'/');
        $r=httpRequest($method,$url,$headers,$payload===null?null:json_encode($payload,JSON_UNESCAPED_SLASHES),20);
        $ok=$r['status']>=200&&$r['status']<300;
        if(!$ok) {
            $message=is_array($r['json'])?(string)($r['json']['message']??'Provider request failed.'):'Provider request failed.';
            return ['ok'=>false,'status'=>$r['status'],'data'=>$r['json'],'error'=>$message];
        }
        return ['ok'=>true,'status'=>$r['status'],'data'=>$r['json'],'error'=>''];
    }
    public function id():string{return 'logspanel';}
    public function label():string{return 'Logspanel';}
    public function schema():array{return ProviderSchema::load('logspanel');}
    public function health():array{ $r=$this->request('GET','/wallet'); return ['ok'=>$r['ok'],'status'=>$r['status'],'message'=>$r['error']!==''?$r['error']:'Connected'];}
    public function walletBalance():?string{ $r=$this->request('GET','/wallet'); return $r['ok']?nairaDecimal((string)($r['data']['data']['balance']??'0')):null; }
    private function paginated(string $path,int $delaySeconds=2):array{
        $items=[];$next=$path;$seen=[];$expectedTotal=null;$lastPage=null;$pages=0;
        for($i=0;$next!==null&&$i<10000;$i++){
            $key=(string)$next;
            if(isset($seen[$key]))throw new RuntimeException('Provider pagination cycle.');
            $seen[$key]=true;
            $r=$this->request('GET',$key);
            if(!$r['ok'])throw new RuntimeException($r['error']);
            $pageData=$r['data']['data']??[];
            if(!is_array($pageData))throw new RuntimeException('Invalid paginated provider response.');
            foreach($pageData as $item)if(is_array($item))$items[]=$item;
            $pages++;
            $meta=is_array($r['data']['meta']??null)?$r['data']['meta']:[];
            if(isset($meta['total'])&&is_numeric($meta['total']))$expectedTotal=(int)$meta['total'];
            if(isset($meta['last_page'])&&is_numeric($meta['last_page']))$lastPage=(int)$meta['last_page'];
            $next=$r['data']['links']['next']??null;
            if($next!==null&&$delaySeconds>0)sleep($delaySeconds);
        }
        if($expectedTotal!==null&&count($items)<$expectedTotal)throw new RuntimeException('Incomplete Logspanel catalog: received '.count($items).' of '.$expectedTotal.'.');
        if($lastPage!==null&&$pages<$lastPage)throw new RuntimeException('Incomplete Logspanel catalog: stopped at page '.$pages.' of '.$lastPage.'.');
        return $items;
    }

    public function parentCategories():array{
        $r=$this->request('GET','/logs/parent-categories');
        return ['categories'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
    }

    public function catalog():array{
        // Logspanel v1 defines /logs/categories as the complete public catalog.
        // Keep the category IDs opaque, including stable negative IDs.
        return $this->paginated('/logs/categories?per_page=100&page=1',2);
    }

    public function productCatalog():array{
        // Optional reference-based representation of the same inventory.
        return $this->paginated('/logs/products?per_page=100&page=1',2);
    }

    public function numberCountries():array{
        $r=$this->request('GET','/numbers/countries');return ['countries'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
    }
    public function numberServices(int $countryId):array{
        $r=$this->request('GET','/numbers/services?country_id='.rawurlencode((string)$countryId));return ['services'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
    }
    public function allNumberServices(array $countries):array{
        $all=[];$errors=[];$count=0;
        foreach($countries as $country){
            $id=(int)($country['id']??0);
            if($id<1)continue;
            $r=$this->numberServices($id);
            $count++;
            if($r['error']!==''){$errors[]='Country '.$id.': '.$r['error'];}
            else foreach($r['services'] as $s){
                if(!is_array($s))continue;
                $s['country_id']=$s['country_id']??$id;
                $s['country_name']=$s['country_name']??(string)($country['name']??'');
                $key=(string)$s['country_id'].':'.(string)($s['service_id']??$s['service_name']??'');
                $all[$key]=$s;
            }
            // Keep the aggregate catalog sync safely below the provider's
            // 60 requests/minute limit even when many countries are enabled.
            if($count< count($countries)) sleep(2);
        }
        return ['services'=>array_values($all),'error'=>implode(' | ',array_unique($errors))];
    }
    public function boostCategories():array{
        $r=$this->request('GET','/boost/categories');return ['categories'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
    }
    public function boostServices(?string $category=null):array{
        $path='/boost/services'.($category!==null&&trim($category)!==''?'?category='.rawurlencode($category):'');$r=$this->request('GET',$path);return ['services'=>is_array($r['data']['data']??null)?$r['data']['data']:[],'error'=>$r['ok']?'':$r['error']];
    }
    public function allBoostServices(array $categories=[]):array{
        $all=[];$errors=[];$r=$this->boostServices();if($r['error']==='')$all=$r['services'];else $errors[]=$r['error'];if(!$all)foreach($categories as $cat){$name=is_array($cat)?(string)($cat['name']??''):(string)$cat;if($name==='')continue;$x=$this->boostServices($name);if($x['error']!==''){$errors[]=$x['error'];continue;}$all=array_merge($all,$x['services']);} $u=[];foreach($all as $s){$id=(string)($s['service_id']??'');if($id!=='')$u[$id]=$s;}return ['services'=>array_values($u),'error'=>implode(' | ',array_unique($errors))];
    }

    public function createOrder(array $payload,string $idempotencyKey):array{
        $send=['quantity'=>(int)($payload['quantity']??1),'idempotency_key'=>$idempotencyKey];
        if(!empty($payload['product_ref']))$send['product_ref']=(string)$payload['product_ref'];
        elseif(isset($payload['category_id']))$send['category_id']=(int)$payload['category_id'];
        else throw new InvalidArgumentException('Logspanel product_ref or category_id is required.');
        return $this->request('POST','/logs/orders',$send,$idempotencyKey);
    }
    public function getOrder(string $providerRef):array{return $this->request('GET','/logs/orders/'.rawurlencode($providerRef));}
    public function createPayment(array $payload,string $idempotencyKey):array{return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Payments are not supported by Logspanel.'];}
    public function verifyPayment(array $payload):array{return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Payments are not supported by Logspanel.'];}
}
