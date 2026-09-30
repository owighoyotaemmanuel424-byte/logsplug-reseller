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
        $key=$this->secret(); if(!$key)return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'SprintPay credential is not configured.'];
        $payload['reference']=$payload['reference']??$idempotencyKey;
        return ['ok'=>true,'status'=>200,'data'=>['redirect_url'=>$this->base().'/pay?amount='.rawurlencode((string)$payload['amount']).'&key='.rawurlencode($key).'&ref='.rawurlencode((string)$payload['reference']).'&email='.rawurlencode((string)($payload['email']??''))],'error'=>''];
    }
    public function verifyPayment(array $payload):array{return ['ok'=>false,'status'=>0,'data'=>null,'error'=>'Payment verification must be performed through the SprintPay webhook.'];}
}
