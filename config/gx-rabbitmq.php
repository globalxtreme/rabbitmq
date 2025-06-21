<?php

return [

    'version' => env('VERSION', 'v1'),

    'connection' => [

        'database' => env('DB_RABBITMQ_CONNECTION', 'rabbitmq'),

        'types' => [

            'global' => [
                'host' => env('RABBITMQ_GLOBAL_HOST', '127.0.0.1'),
                'port' => env('RABBITMQ_GLOBAL_PORT', 5672),
                'user' => env('RABBITMQ_GLOBAL_USER', 'guest'),
                'password' => env('RABBITMQ_GLOBAL_PASSWORD', 'guest'),
                'vhost' => env('RABBITMQ_GLOBAL_VHOST', '/'),
            ],

            'local' => [
                'host' => env('RABBITMQ_LOCAL_HOST', '127.0.0.1'),
                'port' => env('RABBITMQ_LOCAL_PORT', 5672),
                'user' => env('RABBITMQ_LOCAL_USER', 'guest'),
                'password' => env('RABBITMQ_LOCAL_PASSWORD', 'guest'),
                'vhost' => env('RABBITMQ_LOCAL_VHOST', '/'),
            ],

        ],

    ],

    'timeout' => (5 * 60)

];
