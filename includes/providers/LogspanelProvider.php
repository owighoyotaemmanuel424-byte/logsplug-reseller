<?php
declare(strict_types=1);
require_once __DIR__.'/ProviderInterface.php';
require_once __DIR__.'/../naira.php';
require_once __DIR__.'/ProviderSchema.php';
require_once __DIR__.'/../http.php';

final class LogspanelProvider implements ProviderInterface
{
    public function __construct(private array $config, private Closure $secretResolver) {}

    private function base(): string { return rtrim((string)($this->config['base_url']??'https://logspanel.com/api/v1'),'/'); }
    private function request(string $method,string $path,?array $payload=null,?string $idempotency=null): array
    {
        $key=($this->secretResolver)('api_key');
        if(!$key) return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Provider credential is not configured.'];
        $headers=['Accept: application/json','Authorization: Bearer '.$key,'Content-Type: application/json','User-Agent: LogsPlug-Reseller/2.0'];
        if($idempotency)$headers[]='Idempotency-Key: '.$idempotency;
        $r=httpRequest($method,$this->base().'/'.ltrim($path,'/'),$headers,$payload===null?null:json_encode($payload,JSON_UNESCAPED_SLASHES),20);
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
    public function catalog():array{
        $items=[];$next='/logs/products?per_page=100&page=1';$seen=[];
        for($i=0;$next!==null&&$i<10000;$i++){
            if(isset($seen[$next]))throw new RuntimeException('Provider pagination cycle.');
            $seen[$next]=true;$r=$this->request('GET',$next);
            if(!$r['ok'])throw new RuntimeException($r['error']);
            $data=$r['data']['data']??[];if(!is_array($data))throw new RuntimeException('Invalid catalog response.');
            foreach($data as $item)if(is_array($item))$items[]=$item;
            $next=$r['data']['links']['next']??null;
        }
        return $items;
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
