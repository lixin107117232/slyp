<?php

return [
    'autoload' => false,
    'hooks' => [
        'sms_send' => [
            'alisms',
            'hwsms',
        ],
        'sms_notice' => [
            'alisms',
            'hwsms',
        ],
        'sms_check' => [
            'alisms',
            'hwsms',
        ],
        'action_begin' => [
            'clicaptcha',
        ],
        'app_init' => [
            'crontab',
            'epay',
            'log',
            'qiniu',
        ],
        'admin_login_init' => [
            'loginbg',
        ],
        'upload_config_init' => [
            'qiniu',
        ],
        'upload_delete' => [
            'qiniu',
        ],
        'config_init' => [
            'third',
        ],
    ],
    'route' => [
        '/third$' => 'third/index/index',
        '/third/connect/[:platform]' => 'third/index/connect',
        '/third/callback/[:platform]' => 'third/index/callback',
        '/third/bind/[:platform]' => 'third/index/bind',
        '/third/unbind/[:platform]' => 'third/index/unbind',
    ],
    'priority' => [],
];
