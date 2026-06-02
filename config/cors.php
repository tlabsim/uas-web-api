<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://www.nstu.edu.bd',
        'https://profiles.nstu.edu.bd',
        'https://dashboard.nstu.edu.bd',
        'http://web.nstu.local',
        'http://dashboard.nstu.local',
        'http://profiles.nstu.local',
    ],

    'allowed_origins_patterns' => [
        '/^http:\/\/.*\.nstu\.local$/',
        '/^https:\/\/.*\.nstu\.edu\.bd$/',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Webclient-Secret',
        'Authorization',
        'Accept',
        'Origin',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
