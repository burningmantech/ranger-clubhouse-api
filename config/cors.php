<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS
    |--------------------------------------------------------------------------
    |
    | allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
    | to accept any value.
    |
    */

    'paths' => [ '/*' ],

    'supports_credentials' => false,
    'allowed_origins' => ['*'],
    //'allowedOriginsPatterns' => [ 'Content-Type', 'X-Requested-With' ],
    'allowed_origins_patterns' => [ '*' ],
    'allowed_headers' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS', 'PATCH', 'DELETE', 'PUT' ],
    'exposed_headers' => [],
    'max_age' => 3600,
];
