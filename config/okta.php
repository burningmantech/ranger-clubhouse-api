<?php

return [
    'okta' => [
        'base_url' => env('RANGER_CLUBHOUSE_OKTA_BASE_URL', ''),
        'issuer' => env('RANGER_CLUBHOUSE_OKTA_ISSUER', ''),
        'client_id' => env('RANGER_CLUBHOUSE_OKTA_CLIENT_ID', ''),
        'client_secret' => env('RANGER_CLUBHOUSE_OKTA_CLIENT_SECRET', ''),
        'redirect_uri' => env('RANGER_CLUBHOUSE_OKTA_REDIRECT_URI', ''),
    ]
];
