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

    'supportsCredentials' => false,
    'allowedOrigins' => ['*'],
    //'allowedOriginsPatterns' => [ 'Content-Type', 'X-Requested-With' ],
    'allowedOriginsPatterns' => [ '*' ],
    'allowedHeaders' => ['*'],
    'allowedMethods' => ['GET', 'POST', 'OPTIONS', 'PATCH', 'DELETE', 'PUT' ],
    'exposedHeaders' => [],
    'maxAge' => 3600,
];
