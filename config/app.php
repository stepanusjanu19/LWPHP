<?php

/**
 * Application configuration.
 *
 * env() and storage_path() helpers are loaded in public/index.php
 * before this file is ever parsed by ConfigLoader.
 *
 * DI bindings live exclusively in config/di/definitions.php.
 */
return [
    'name' => env('APP_NAME', 'LWPHP'),
    'debug' => env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'env' => env('APP_ENV', 'development'),

    'session' => [
        'driver' => env('SESSION_DRIVER', 'file'),
        'lifetime' => (int) env('SESSION_LIFETIME', 120),
        'cookie' => env('SESSION_COOKIE', 'lwphp_session'),
        'path' => storage_path('sessions'),
    ],

    'cache' => [
        'default' => env('CACHE_DRIVER', 'file'),
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => storage_path('framework/cache'),
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => env('REDIS_URL', 'tcp://127.0.0.1:6379'),
                'ttl' => (int) env('REDIS_TTL', 3600),
            ],
            'memcached' => [
                'driver' => 'memcached',
                'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                'port' => (int) env('MEMCACHED_PORT', 11211),
            ],
        ],
    ],

    'logging' => [
        'channel' => env('LOG_CHANNEL', 'stack'),
        'level' => env('LOG_LEVEL', 'debug'),
        'path' => storage_path('logs/app.log'),
    ],
];
