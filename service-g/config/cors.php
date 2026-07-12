<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Nuxt dev-server (:3000) и gateway (listtodo.localhost:8080) обращаются к API
    | с credentials — origins задаются через CORS_ALLOWED_ORIGINS.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:3000,http://listtodo.localhost:8080,http://listtodo.localhost',
        )),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
