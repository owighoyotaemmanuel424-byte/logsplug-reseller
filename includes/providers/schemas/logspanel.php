<?php
return [
    'id'=>'logspanel',
    'label'=>'Logspanel',
    'base_url'=>'https://logspanel.com/api/v1',
    'secret_env'=>'LOGSPANEL_API_KEY',
    'secret_label'=>'API Key',
    'capabilities'=>['catalog','orders','wallet'],
    'fields'=>[
        ['name'=>'base_url','label'=>'API Base URL','type'=>'url','required'=>true,'default'=>'https://logspanel.com/api/v1'],
        ['name'=>'enabled','label'=>'Enabled','type'=>'boolean','required'=>false],
    ],
];
