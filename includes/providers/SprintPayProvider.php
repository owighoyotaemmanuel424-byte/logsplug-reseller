<?php
declare(strict_types=1);
require_once __DIR__.'/ProviderInterface.php';
require_once __DIR__.'/ProviderSchema.php';
require_once __DIR__.'/../http.php';

final class SprintPayProvider implements ProviderInterface
{
    public function __construct(private array $config, private Closure $secretResolver) {}
    private function base():string{return rtrim((string)($this->config['base_url']??'https://web.sprintpay.online'),'/');}
    private function secret():?string{return ($this->secretResolver)('api_key');}
    private function unsupported():array{return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'SprintPay operation is not available through the configured API adapter.'];}
    public function id():string{return 'sprintpay';}
    public function label():string{return 'SprintPay';}
    public function schema():array{return ProviderSchema::load('sprintpay');}
    public function health():array{return ['ok'=>$this->secret()!==null,'status'=>0,'message'=>$this->secret()!==null?'Configured':'Not configured'];}
    public function walletBalance():?string{return null;}
    public function catalog():array{return [];}
    public function createOrder(array $payload,string $idempotencyKey):array{return $this->unsupported();}
    public function getOrder(string $providerRef):array{return $this->unsupported();}
    public function createPayment(array $payload,string $idempotencyKey):array{
        $key=$this->secret();$endpoint=trim((string)($this->config['payment_endpoint']??''));
        if(!$key||$endpoint==='')return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'SprintPay server-side payment endpoint is not configured.'];
        $body=json_encode([
            'amount'=>(string)$payload['amount'],'reference'=>(string)($payload['reference']??$idempotencyKey),
            'email'=>(string)($payload['email']??''),'callback_url'=>(string)($payload['callback_url']??($this->config['callback_url']??'')),
        ],JSON_UNESCAPED_SLASHES);
        $r=httpRequest('POST',$endpoint,['Accept: application/json','Authorization: Bearer '.$key,'Content-Type: application/json','Idempotency-Key: '.$idempotencyKey],$body,20);
        $data=$r['json'];
        if($r['status']<200||$r['status']>=300||!is_array($data))return ['ok'=>false,'status'=>$r['status'],'data'=>$data,'error'=>'SprintPay payment initiation failed.'];
        $redirect=(string)($data['data']['redirect_url']??$data['redirect_url']??'');
        if($redirect==='')return ['ok'=>false,'status'=>$r['status'],'data'=>$data,'error'=>'SprintPay did not return a hosted checkout URL.'];
        return ['ok'=>true,'status'=>$r['status'],'data'=>['redirect_url'=>$redirect],'error'=>''];
    }
    public function verifyPayment(array $payload):array{return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Payment verification must be performed through the SprintPay webhook.'];}
}
