<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    // Note photo storage is controlled thru the PhotoStorage setting in config/clubhouse.php

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "s3", "rackspace"
    |
    */

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path(''),
            'url' => env('APP_URL'),
            'visibility' => 'public',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'photos-local' => [
            'driver' => 'local',
            'root' => storage_path(''),
            'url' => env('APP_URL').'/',
            'visibility' => 'public',
            'throw' => false,
        ],

        'photos-s3' => [
            'driver' => 's3',
            'key' => env('RANGER_CLUBHOUSE_S3_ACCESS_KEY', ''),
            'secret' => env('RANGER_CLUBHOUSE_S3_ACCESS_SECRET', ''),
            'region' => env('RANGER_CLUBHOUSE_S3_DEFAULT_REGION', 'us-west-2'),
            'bucket' => env('RANGER_CLUBHOUSE_S3_BUCKET', 'ranger-photos'),
            'url' => env('RANGER_CLUBHOUSE_S3_URL', 'https://ranger-photos.s3-us-west-2.amazonaws.com'),
            'visibility' => 'public',
            'throw' => false,
        ],

        'bmid-export-local' => [
            'driver' => 'local',
            'root' => storage_path(''),
            'url' => env('APP_URL').'/',
            'visibility' => 'public',
            'throw' => false,
        ],

        'bmid-export-s3' => [
            'driver' => 's3',
            'key' => env('RANGER_CLUBHOUSE_S3_BMID_ACCESS_KEY', ''),
            'secret' => env('RANGER_CLUBHOUSE_S3_BMID_ACCESS_SECRET', ''),
            'region' => env('RANGER_CLUBHOUSE_S3_BMID_DEFAULT_REGION', 'us-west-2'),
            'bucket' => env('RANGER_CLUBHOUSE_S3_BMID_BUCKET', 'ranger-photos'),
            'url' => env('RANGER_CLUBHOUSE_S3_BMID_URL', 'https://ranger-photos.s3-us-west-2.amazonaws.com'),
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('RANGER_CLUBHOUSE_S3_ACCESS_KEY', ''),
            'secret' => env('RANGER_CLUBHOUSE_S3_ACCESS_SECRET', ''),
            'region' => env('RANGER_CLUBHOUSE_S3_DEFAULT_REGION', 'us-west-2'),
            'bucket' => env('RANGER_CLUBHOUSE_S3_BUCKET', 'ranger-photos'),
            'url' => env('RANGER_CLUBHOUSE_S3_URL', 'https://ranger-photos.s3-us-west-2.amazonaws.com'),
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
];
