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

    // GroundhogDay Server support - if true, current_year() will pull the year from
    // the database running with the faketime shim.
    'GroundhogDayServer'    => env('RANGER_CLUBHOUSE_GROUNDHOG_DAY_SERVER', false),

];
