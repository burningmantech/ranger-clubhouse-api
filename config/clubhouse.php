<?php

// NOTE: Entries in .env (if any) will overwrite settings here.
// .env is to be customized on each machine, and should not be
// checked in to the source repository.

return [
    'VCSRevision' => 'DEVELOPMENT',

    // when the system is taken on-site, set OnPlaya = TRUE on the server that will
    // be in Black Rock City and set ReadOnly = TRUE on the system that remains
    // available to the internet so folks can check their schedules
    'OnPlaya'  => env('RANGER_CLUBHOUSE_ON_PLAYA', false),
    'ReadOnly' => env('RANGER_CLUBHOUSE_READ_ONLY', false),

    'PhotoSource'       => env('RANGER_CLUBHOUSE_PHOTO_SOURCE', 'Lambase'),
    'PhotoUploadEnable' => env('RANGER_CLUBHOUSE_PHOTO_ENABLE_UPLOAD', true),
    'LambaseStatusUrl'  => env('RANGER_CLUBHOUSE_LAMBASE_STATUS_URL', 'http://www.tanis.com/brclam/webservice.cfc'),
    'LambaseReportUrl'  => env('RANGER_CLUBHOUSE_LAMBASE_REPORT_URL', 'http://www.tanis.com/brclam/webservice_rpt.cfc'),
    'LambaseImageUrl'   => env('RANGER_CLUBHOUSE_LAMBASE_IMAGE_URL', 'http://www.lambase.com/lam_photos/rangers'),
    'LambaseJumpinUrl'  => env('RANGER_CLUBHOUSE_LAMBASE_JUMP_URL', 'http://tanis.com/brclam/jumpin_ranger.cfm'),

    'TimesheetCorrectionEnable'  => env('RANGER_CLUBHOUSE_TIMESHEET_CORRECTION_ENABLE', true),
    'TimesheetCorrectionYear'    => env('RANGER_CLUBHOUSE_TIMESHEET_CORRECTION_YEAR', 2018),

    // Suggestion responses spreadsheet is in the Ranger Teams > Ranger Tech > 2015 Event folder in the burningman.org Google Drive
    'ClubhouseSuggestionUrlTemplate' => env('RANGER_CLUBHOUSE_TIMESHEET_CORRECTION_TEMPLATE_URL', 'https://docs.google.com/forms/d/1Rox154ty2thJThS5KE-zapNKi4Rcsjgg094YyoTqeWQ/viewform?entry.561095571={callsign}&entry.1642048126={email}'),

    // The 2015 shift log is the "Messages" section on the HQShiftLog user
    'ShiftLogUrl' => env('RANGER_CLUBHOUSE_SHIFT_LOG_URL', '?DMSc=person&DMSm=select&personId=3951#messages'),

    // Ranger Policies Link
    'RangerPoliciesUrl' => env('RANGER_CLUBHOUSE_POLICIES_URL', 'https://drive.google.com/drive/u/1/folders/0B9mqynALmDHDQ2VvaFB3SnVvMTg?ogsrc=32'),

    // How to join Ranger special teams
    'JoiningRangerSpecialTeamsUrl' => env('RANGER_CLUBHOUSE_SPECIAL_TEAMS_URL', 'https://docs.google.com/document/d/1xEVnm1SdcyvLnsUYwL5v_WxO1zy3yhMkAmbXIU_0yqc'),

    // Motorpool policy form
    'MotorpoolPolicyUrl' => env('RANGER_CLUBHOUSE_MOTORPOOL_POLICY_URL', "https://docs.google.com/forms/d/1wEn544ZcpdWuvSxCYpoX5uAS_CeXitEsJJGwaPCpl-I/viewform"),

    // Manual review Google sheet
    'ManualReviewLinkEnable'            => env('RANGER_CLUBHOUSE_REVIEW_ENABLE', false),
    // If true, allow shift signups even if manual review is disabled
    'ManualReviewDisabledAllowSignups'  => env('RANGER_CLUBHOUSE_REVIEW_ALLOW_SIGNUPS', true),
    'ManualReviewProspectiveAlphaLimit' => env('RANGER_CLUBHOUSE_REVIEW_MAX_ALPHAS', 177),
    'ManualReviewGoogleFormBaseUrl'     => env('RANGER_CLUBHOUSE_REVIEW_FORM_URL', 'https://docs.google.com/forms/d/e/1FAIpQLScNcr1xZ9YHULag7rdS5-LUU_e1G1XS5kfDI85T10RVTAeZXA/viewform?entry.960989731='),
    'ManualReviewGoogleSheetId'         => env('RANGER_CLUBHOUSE_REVIEW_SHEET_ID', '1T6ZoSHoQhjlcqOs0J-CQ7HoMDQd_RGs4y_SQSvOb15M'),

    // Google Sheets Credentials
    'ManualReviewAuthConfig' => env('RANGER_CLUBHOUSE_REVIEW_AUTH_CONFIG', ''),


    // Salesforce sandbox (sbx) parameters
    // Note you must set SFsbxPassword in local.config.php!
    'SFsbxClientId'     => env('RANGER_CLUBHOUSE_SALESFORCE_SBX_CLIENT_ID', '3MVG9zZht._ZaMunO578Jy7KHxp2oy5KMiCnRBHoLOUKvk6hUB3jHcmgsnMImbo30PPXFL4p5qUSYrMdYQ_C5'),
    'SFsbxClientSecret' => env('RANGER_CLUBHOUSE_SALESFORCE_SBX_CLIENT_SECRET', '90600550470841821'),
    'SFsbxUsername'     => env('RANGER_CLUBHOUSE_SALESFORCE_SBX_USER', 'philapi@burningman.com.dev3'),
    'SFsbxAuthUrl'      => env('RANGER_CLUBHOUSE_SALESFORCE_SBX_AUTH_URL', 'https://test.salesforce.com/services/oauth2/token'),
    'SFsbxPassword'     => env('RANGER_CLUBHOUSE_SALESFORCE_SBX_PASSWORD', '*'),

    // Salesforce production (prd) parameters
    // Note you must set SFprdPassword in local.config.php!
    'SFprdClientId'      => env('RANGER_CLUBHOUSE_SALESFORCE_PRD_PASSWORD', '3MVG9rFJvQRVOvk6W.N0QISwohURI.shVyYx2vyhMlZ_39Wi9wohZYuDbY5Fuhd_0sOCRB1.jn.ijRia1F0Cd'),
    'SFprdClientSecret'  => env('RANGER_CLUBHOUSE_SALESFORCE_PRD_CLIENT_SECRET', '7358471550048400762'),
    'SFprdUsername'      => env('RANGER_CLUBHOUSE_SALESFORCE_PRD_USER', 'diverdave@burningman.com'),
    'SFprdAuthUrl'       => env('RANGER_CLUBHOUSE_SALESFORCE_PRD_AUTH_URL', 'https://login.salesforce.com/services/oauth2/token'),
    'SFprdPassword'      => env('RANGER_CLUBHOUSE_SALESFORCE_PRD_PASSWORD', '*'),
    'SFEnableWritebacks' => env('RANGER_CLUBHOUSE_SALESFORCE_ENABLE_WRITEBACKS', false),

    // Tickets, Vehicle Passes, Work Access Passes
    'TicketsAndStuffEnable'    => env('RANGER_CLUBHOUSE_TAS_ENABLE', true),  // Menu item
    'TicketsAndStuffEnablePNV' => env('RANGER_CLUBHOUSE_TAS_ENABLE_PNV', true),  // Menu item for prospectives and alphas
    'TAS_SubmitDate'           => env('RANGER_CLUBHOUSE_TAS_SUBMIT_DATE', '2018-07-16 23:59:00'),
    'TAS_Tickets'              => env('RANGER_CLUBHOUSE_TAS_TICKETS', 'accept'),  // Or 'accept' or 'frozen' or 'none'
    'TAS_VP'                   => env('RANGER_CLUBHOUSE_TAS_VP', 'accept'),  // Or 'accept' or 'frozen' or 'none'
    'TAS_WAP'                  => env('RANGER_CLUBHOUSE_TAS_WAP', 'accept'),  // Or 'accept' or 'frozen' or 'none'
    'TAS_WAPSO'                => env('RANGER_CLUBHOUSE_TAS_WAP_SO', 'frozen'),  // Or 'accept' or 'frozen' or 'none'
    'TAS_WAPSOMax'             => env('RANGER_CLUBHOUSE_TAS_WAP_SO_MAX', 3),  // Max # of SO WAPs
    'TAS_BoxOfficeOpenDate'    => env('RANGER_CLUBHOUSE_TAS_WAP_BOXOFFICE_OPEN_DATE', '2017-08-22 12:00:00'),
    'TAS_DefaultWAPDate'       => env('RANGER_CLUBHOUSE_TAS_WAP_DEFAULT_DATE', '2017-08-24'),
    'TAS_DefaultAlphaWAPDate'  => env('RANGER_CLUBHOUSE_TAS_WAP_DEFAULT_ALPHA_DATE', '2017-08-25'),
    'TAS_DefaultSOWAPDate'     => env('RANGER_CLUBHOUSE_TAS_WAP_DEFAULT_SO_DATE', '2017-08-24'),
    'TAS_Email'                => env('RANGER_CLUBHOUSE_TAS_EMAIL', 'ranger-ticketing-stuff@burningman.org'),
    'TAS_Ticket_FAQ'           => env('RANGER_CLUBHOUSE_TAS_FAQ_URL', 'https://docs.google.com/document/d/1TILtNyPUygjVk9T0B7FEobwAwKOBTub-YewyMtJaIFs/edit'),
    'TAS_WAP_FAQ'              => env('RANGER_CLUBHOUSE_TAS_WAP_FAQ_URL', 'https://docs.google.com/document/d/1wuucvq017bQHP7-0uH2KlSWSaYW7CSvNN7siU11Ah7k/edit'),
    'TAS_VP_FAQ'               => env('RANGER_CLUBHOUSE_TAS_WAP_VP_URL', 'https://docs.google.com/document/d/1KPBD_qdyBkdDnlaVBTAVX8-U3WWcSXa-4_Kf48PbOCM/edit'),
    'TAS_Alpha_FAQ'            => env('RANGER_CLUBHOUSE_TAS_ALPHA_FAQ_URL', 'https://docs.google.com/document/d/1yyIAUqP4OdjGTZeOqy1PxE_1Hynhkh0cFzALQkTM-ds/edit'),

    // Meal date info (needs to change every year)
    'MealInfoAvailable'       => env('RANGER_CLUBHOUSE_MEAL_INFO_AVAILABLE', false),
    'MealDates'               => env('RANGER_CLUBHOUSE_MEAL_DATES', 'Pre Event is Tue 8/8 (dinner) - Sun 8/27; During Event is Mon 8/28 - Mon 9/4; Post Event is Tue 9/5 - Sat 9/9 (lunch)'),

    'RadioCheckoutFormUrl'    => env('RANGER_CLUBHOUSE_CHECKOUT_FORM_URL', 'https://drive.google.com/open?id=1p4EerIDN6ZRxcWkI7tDFGuMf6SgL1LTQ'),

    'SiteNotice'               => env('RANGER_COPYRIGHT_NOTICE', 'Copyright 2008-2018 Black Rock City, LLC. All information contained within this website is strictly confidential.'),

    'SiteTitle'                => 'Black Rock Rangers Secret Clubhouse',
    'AdminEmail'               => env('RANGER_CLUBHOUSE_EMAIL_ADMIN', 'ranger-tech-ninjas@burningman.org'),
    'GeneralSupportEmail'      => env('RANGER_CLUBHOUSE_EMAIL_SUPPORT', 'rangers@burningman.org'),
    'VCEmail'                  => env('RANGER_CLUBHOUSE_EMAIL_VC', 'ranger-vc-list@burningman.org'),
    'SignupUrl'                => 'http://jousting-at-windmills.org/clubhouse/',
    'SendWelcomeEmail'         => env('RANGER_CLUBHOUSE_SEND_WELCOME_EMAIL', false),
    'SqlDateFormatLiteral'     => "'%Y-%m-%d'",
    'SqlDateTimeFormatLiteral' => "'%a %b %d @ %H:%i'",
    'SqlTimeFormatLiteral'     => "'%H:%i'",
    'TimeZone'                 => env('RANGER_CLUBHOUSE_TIMEZONE', 'America/Los_Angeles'),

    // if true all email broadcasts will log the email but not actually send it
    'BroadcastMailSandbox'     => env('RANGER_CLUBHOUSE_BROADCAST_MAIL_SANDBOX', false),

    // if true all clubhouse message broadcasts will the log message but not send it.
    'BroadcastClubhouseSandbox'=> env('RANGER_CLUBHOUSE_BROADCAST_CLUBHOUSE_SANDBOX', false),

    // if true any Clubhouse message created will be notified thru the RBS
    'BroadcastClubhouseNotify' => env('RANGER_CLUBHOUSE_MESSAGE_NOTIFY', false),

    // Twilio Configuration
    'TwilioAccountSID' => env('RANGER_CLUBHOUSE_TWILIO_ACCOUNT_SID', ''),
    'TwilioAuthToken'  => env('RANGER_CLUBHOUSE_TWILIO_AUTH_TOKEN', ''),
    'TwilioServiceId'  => env('RANGER_CLUBHOUSE_TWILIO_SERVICE_ID', ''),

    // Optional ticket credit warning messages.
    // If any are not set, no message will be displayed
    'RpTicketThreshold' => env('RANGER_CLUBHOUSE_THRESHOLD_RPT', 19),  // Ticket threshold for reduced price
    'ScTicketThreshold' => env('RANGER_CLUBHOUSE_THRESHOLD_CRED', 38),  // Ticket threshold for staff credential
    'YrTicketThreshold' => env('RANGER_CLUBHOUSE_THRESHOLD_YEAR', 2018),  // Ticket threshold year

    // Development flags
    'DevShowSql' => false,  // if true - Show all SQL requests on the developr console or web log.
    'DevLogSql'  => false,    // if true log all SQL statements to the log table
];
