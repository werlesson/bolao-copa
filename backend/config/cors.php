<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Lista explícita: com ['*'] o preflight devolve só um método e o Chrome pode bloquear PATCH.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_unique([
        env('FRONTEND_URL', 'http://localhost:3000'),
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
