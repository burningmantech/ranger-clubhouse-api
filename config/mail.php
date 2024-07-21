<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail Driver
    |--------------------------------------------------------------------------
    |
    | Laravel supports both SMTP and PHP's "mail" function as drivers for the
    | sending of e-mail. You may specify which one you're using throughout
    | your application here. By default, Laravel is setup for SMTP mail.
    |
    | Supported: "smtp", "sendmail", "mailgun", "mandrill", "ses",
    |            "sparkpost", "log", "array"
    |
    */

    'default' => env('RANGER_CLUBHOUSE_MAIL_DRIVER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses",
    |            "postmark", "log", "array", "failover"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('RANGER_CLUBHOUSE_SMTP_SERVER', 'localhost'),
            'port' => env('RANGER_CLUBHOUSE_SMTP_PORT', 1025),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('RANGER_CLUBHOUSE_SMTP_USERNAME'),
            'password' => env('RANGER_CLUBHOUSE_SMTP_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            // Symphony Mailer 7.x has a bug where a TLS connection is attempted
            // regardless of how the tls flag is set. auto_tls has to be explicitly set to false so
            // TLS negotiation is disabled.
            // On the playa, a local outbound only mail server is used to send mail. It doesn't make
            // any sense to use a encrypted connection when mail server is isolated in a docker instance.
            // Plus setting up a SSL certificate would be a real PITA.
            'auto_tls' => env('RANGER_CLUBHOUSE_SMTP_PORT') != 25,
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('RANGER_CLUBHOUSE_EMAIL_SUPPORT', 'do-not-reply@burningmail.burningman.org'),
        'name' => env('RANGER_CLUBHOUSE_NAME_SUPPORT', 'The Black Rock Rangers'),
    ],

    /*
      Primarily used by the RBS to limit how many emails can be sent per
      connection.
    */

    'messages_per_connection' => 50,
];
