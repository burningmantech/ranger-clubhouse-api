<?php

/*
 * NOTE: Entries in .env (if any) will overwrite settings here.
 * .env is to be customized on each machine, and should not be
 * checked in to the source repository.
 *
 * ALL ENTRIES HERE WILL BE PASSED UP TO THE CLIENT AS IS. DO NOT PLACE ANY
 * CREDENTIALS OR OTHER SENSITVE INFORMATION IN THIS FILE.
 */

return [
    'VCSRevision' => 'DEVELOPMENT',

    // when the system is taken on-site, set OnPlaya = TRUE on the server that will
    // be in Black Rock City and set ReadOnly = TRUE on the system that remains
    // available to the internet so folks can check their schedules
    'OnPlaya'  => env('RANGER_CLUBHOUSE_ON_PLAYA', false),
    'ReadOnly' => env('RANGER_CLUBHOUSE_READ_ONLY', false),

    // Ranger Policies Link
    'RangerPoliciesUrl' => env('RANGER_CLUBHOUSE_POLICIES_URL', 'https://drive.google.com/drive/u/1/folders/0B9mqynALmDHDQ2VvaFB3SnVvMTg?ogsrc=32'),

    // How to join Ranger special teams
    'JoiningRangerSpecialTeamsUrl' => env('RANGER_CLUBHOUSE_SPECIAL_TEAMS_URL', 'https://docs.google.com/document/d/1xEVnm1SdcyvLnsUYwL5v_WxO1zy3yhMkAmbXIU_0yqc'),

    // Meal date info (needs to change every year)
    'MealInfoAvailable'    => env('RANGER_CLUBHOUSE_MEAL_INFO_AVAILABLE', false),
    'MealDates'            => env('RANGER_CLUBHOUSE_MEAL_DATES', 'Pre Event is Tue 8/8 (dinner) - Sun 8/27; During Event is Mon 8/28 - Mon 9/4; Post Event is Tue 9/5 - Sat 9/9 (lunch)'),

    'RadioCheckoutFormUrl' => env('RANGER_CLUBHOUSE_CHECKOUT_FORM_URL', 'https://drive.google.com/open?id=1p4EerIDN6ZRxcWkI7tDFGuMf6SgL1LTQ'),

    'SiteNotice'           => env('RANGER_COPYRIGHT_NOTICE', 'Copyright 2008-2018 Black Rock City, LLC. All information contained within this website is strictly confidential.'),

    'SiteTitle'            => 'Black Rock Rangers Secret Clubhouse',

    'MotorpoolPolicyEnable' => env('RANGER_CLUBHOUSE_MOTORPOOL_POLICY_ENABLE', false),

    // Optional ticket credit warning messages.
    // If any are not set, no message will be displayed
    'RpTicketThreshold' => env('RANGER_CLUBHOUSE_THRESHOLD_RPT', 19),  // Ticket threshold for reduced price
    'ScTicketThreshold' => env('RANGER_CLUBHOUSE_THRESHOLD_CRED', 38),  // Ticket threshold for staff credential
    'YrTicketThreshold' => env('RANGER_CLUBHOUSE_THRESHOLD_YEAR', 2019),  // Ticket threshold year

    'DualClubhouse'     => env('RANGER_CLUBHOUSE_DUAL_CLUBHOUSE', false),
    'ClassicClubhouseUrl'   => env('RANGER_CLUBHOUSE_CLASSIC_URL', ''),
];
