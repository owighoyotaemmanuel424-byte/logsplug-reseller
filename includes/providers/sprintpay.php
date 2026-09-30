<?php
return [
    'id'=>'sprintpay',
    'label'=>'SprintPay',
    'base_url'=>'https://web.sprintpay.online',
    'secret_env'=>'SPRINTPAY_SECRET_KEY',
    'secret_label'=>'Secret Key',
    'capabilities'=>['payments'],
    'fields'=>[
        ['name'=>'base_url','label'=>'Payment Base URL','type'=>'url','required'=>true,'default'=>'https://web.sprintpay.online'],
        ['name'=>'callback_url','label'=>'Callback URL','type'=>'url','required'=>true],
        ['name'=>'enabled','label'=>'Enabled','type'=>'boolean','required'=>false],
    ],
];
