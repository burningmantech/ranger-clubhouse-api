<?php

/*
 * Clubhouse configs related to the development environment and/or the operating platform.
 *
 * All other Clubhouse configuration variables are stored in the database.
 * see app/Models/Setting.php for more information.
 */

return [
    // The deployment environment: Production / Staging / etc
    // Passed to the client and not used by the backend.
    'DeploymentEnvironment' => env('RANGER_CLUBHOUSE_ENVIRONMENT', ''),

    // GroundhogDay Server (string) - if set will turn on
    'GroundhogDayTime'    => env('RANGER_CLUBHOUSE_GROUNDHOG_DAY_TIME', ''),

    'RekognitionAccessKey' => env('RANGER_CLUBHOUSE_REKOGNITION_ACCESS_KEY', ''),
    'RekognitionAccessSecret' => env('RANGER_CLUBHOUSE_REKOGNITION_ACCESS_SECRET', ''),

    /*
     * What config/filesystem.php driver to use for photo storage.
     */
    'PhotoStorage' => env('RANGER_CLUBHOUSE_PHOTO_STORAGE', 'photos-local'),

    /*
     * Where to store the BMID exports
     */
    'BmidExportStorage' => env('RANGER_CLUBHOUSE_PHOTO_STORAGE', 'bmid-export-local')
];
