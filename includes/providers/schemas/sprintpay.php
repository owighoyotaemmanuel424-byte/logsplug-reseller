<?php
return [
 'id'=>'sprintpay','label'=>'SprintPay','secret_env'=>'SPRINTPAY_SECRET_KEY','secret_name'=>'api_key',
 'capabilities'=>['payments'],
 'base_url'=>'https://web.sprintpay.online',
 'fields'=>[
  ['name'=>'base_url','label'=>'Payment Base URL','type'=>'url','required'=>true,'default'=>'https://web.sprintpay.online'],
  ['name'=>'callback_url','label'=>'Callback URL','type'=>'url','required'=>true,'default'=>''],
  ['name'=>'enabled','label'=>'Enabled','type'=>'boolean','required'=>false,'default'=>'0'],
 ],
];
