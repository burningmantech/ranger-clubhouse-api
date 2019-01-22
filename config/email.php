<?php

/*
 * Various email addresses..shared between the client and server.
 *
 * DO NOT PUT ANY SENSITIVE ADDRESSES IN THE FILE. The configuration will be sent
 * to the client as-is.
 */

return [
    'AdminEmail'           => env('RANGER_CLUBHOUSE_EMAIL_ADMIN', 'ranger-tech-ninjas@burningman.org'),
    'GeneralSupportEmail'  => env('RANGER_CLUBHOUSE_EMAIL_SUPPORT', 'rangers@burningman.org'),
    'PersonnelEmail'       => env('RANGER_CLUBHOUSE_EMAIL_PERSONNEL', 'ranger-personnel@burningman.org'),
    'VCEmail'              => env('RANGER_CLUBHOUSE_EMAIL_VC', 'ranger-vc-list@burningman.org'),
];
