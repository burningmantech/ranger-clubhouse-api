<?php

use Illuminate\Database\Seeder;

class SettingTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        DB::table('setting')->delete();

        DB::table('setting')->insert(array(
            0 =>
                array(
                    'id' => 1,
                    'name' => 'OnPlaya',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            1 =>
                array(
                    'id' => 2,
                    'name' => 'AllowSignupsWithoutPhoto',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            2 =>
                array(
                    'id' => 3,
                    'name' => 'ReadOnly',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            5 =>
                array(
                    'id' => 13,
                    'name' => 'TimesheetCorrectionEnable',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            6 =>
                array(
                    'id' => 14,
                    'name' => 'TimesheetCorrectionYear',
                    'value' => '2019',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            7 =>
                array(
                    'id' => 15,
                    'name' => 'ClubhouseSuggestionUrlTemplate',
                    'value' => 'http://example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            8 =>
                array(
                    'id' => 16,
                    'name' => 'RangerPoliciesUrl',
                    'value' => 'http://example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            9 =>
                array(
                    'id' => 17,
                    'name' => 'JoiningRangerSpecialTeamsUrl',
                    'value' => 'http://example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            10 =>
                array(
                    'id' => 18,
                    'name' => 'PNVWaitList',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            11 =>
                array(
                    'id' => 25,
                    'name' => 'SFsbxClientId',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            12 =>
                array(
                    'id' => 26,
                    'name' => 'SFsbxClientSecret',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            13 =>
                array(
                    'id' => 27,
                    'name' => 'SFsbxUsername',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            14 =>
                array(
                    'id' => 28,
                    'name' => 'SFsbxAuthUrl',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            15 =>
                array(
                    'id' => 29,
                    'name' => 'SFsbxPassword',
                    'value' => '*',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            16 =>
                array(
                    'id' => 30,
                    'name' => 'SFprdClientId',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            17 =>
                array(
                    'id' => 31,
                    'name' => 'SFprdClientSecret',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            18 =>
                array(
                    'id' => 32,
                    'name' => 'SFprdUsername',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            19 =>
                array(
                    'id' => 33,
                    'name' => 'SFprdAuthUrl',
                    'value' => 'https://login.salesforce.com/services/oauth2/token',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            20 =>
                array(
                    'id' => 34,
                    'name' => 'SFprdPassword',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => '2020-03-17 09:46:46',
                ),
            21 =>
                array(
                    'id' => 35,
                    'name' => 'SFEnableWritebacks',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => '2020-03-17 09:45:52',
                ),
            22 =>
                array(
                    'id' => 36,
                    'name' => 'TicketingPeriod',
                    'value' => 'closed',
                    'created_at' => NULL,
                    'updated_at' => '2020-03-27 08:17:28',
                ),
            23 =>
                array(
                    'id' => 37,
                    'name' => 'TicketsAndStuffEnablePNV',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            24 =>
                array(
                    'id' => 38,
                    'name' => 'TAS_SubmitDate',
                    'value' => '2019-07-14 23:59:00',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            25 =>
                array(
                    'id' => 39,
                    'name' => 'TAS_Tickets',
                    'value' => 'accept',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            26 =>
                array(
                    'id' => 40,
                    'name' => 'TAS_VP',
                    'value' => 'accept',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            27 =>
                array(
                    'id' => 41,
                    'name' => 'TAS_WAP',
                    'value' => 'accept',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            28 =>
                array(
                    'id' => 42,
                    'name' => 'TAS_WAPSO',
                    'value' => 'accept',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            29 =>
                array(
                    'id' => 43,
                    'name' => 'TAS_Delivery',
                    'value' => 'accept',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            30 =>
                array(
                    'id' => 44,
                    'name' => 'TAS_WAPSOMax',
                    'value' => '3',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            31 =>
                array(
                    'id' => 45,
                    'name' => 'TAS_BoxOfficeOpenDate',
                    'value' => '2019-08-19 12:00:00',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            32 =>
                array(
                    'id' => 46,
                    'name' => 'TAS_DefaultWAPDate',
                    'value' => '2020-08-27',
                    'created_at' => NULL,
                    'updated_at' => '2020-02-02 15:51:05',
                ),
            33 =>
                array(
                    'id' => 47,
                    'name' => 'TAS_DefaultAlphaWAPDate',
                    'value' => '2020-08-28',
                    'created_at' => NULL,
                    'updated_at' => '2020-02-02 15:50:51',
                ),
            34 =>
                array(
                    'id' => 48,
                    'name' => 'TAS_DefaultSOWAPDate',
                    'value' => '2020-08-27',
                    'created_at' => NULL,
                    'updated_at' => '2020-02-02 15:50:59',
                ),
            35 =>
                array(
                    'id' => 49,
                    'name' => 'TAS_Email',
                    'value' => 'ranger-ticketing-stuff@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            36 =>
                array(
                    'id' => 50,
                    'name' => 'TAS_Ticket_FAQ',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            37 =>
                array(
                    'id' => 51,
                    'name' => 'TAS_WAP_FAQ',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            38 =>
                array(
                    'id' => 52,
                    'name' => 'TAS_VP_FAQ',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            39 =>
                array(
                    'id' => 53,
                    'name' => 'TAS_Alpha_FAQ',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            40 =>
                array(
                    'id' => 54,
                    'name' => 'TicketVendorName',
                    'value' => 'Burning Man Ticketing System',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            41 =>
                array(
                    'id' => 55,
                    'name' => 'TicketVendorEmail',
                    'value' => 'ticketsupport@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            42 =>
                array(
                    'id' => 56,
                    'name' => 'RpTicketThreshold',
                    'value' => '19',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            43 =>
                array(
                    'id' => 57,
                    'name' => 'ScTicketThreshold',
                    'value' => '38',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            44 =>
                array(
                    'id' => 58,
                    'name' => 'YrTicketThreshold',
                    'value' => '2019',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            45 =>
                array(
                    'id' => 59,
                    'name' => 'MealInfoAvailable',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            46 =>
                array(
                    'id' => 60,
                    'name' => 'MealDates',
                    'value' => 'Pre-Event is Weds 8/7 (dinner) - Sat 8/24 (dinner); During Event is Sun 8/25 (breakfast) - Mon 9/2 (dinner); Post Event is Tues 9/5 (breakfast) - Sat 9/7 (lunch)',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            47 =>
                array(
                    'id' => 61,
                    'name' => 'RadioInfoAvailable',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            48 =>
                array(
                    'id' => 62,
                    'name' => 'RadioCheckoutFormUrl',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            49 =>
                array(
                    'id' => 63,
                    'name' => 'MotorpoolPolicyEnable',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => '2020-01-07 18:18:38',
                ),
            50 =>
                array(
                    'id' => 64,
                    'name' => 'TrainingSignupFromEmail',
                    'value' => 'do-no-reply@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            51 =>
                array(
                    'id' => 65,
                    'name' => 'ShiftSignupFromEmail',
                    'value' => 'do-not-reply@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            52 =>
                array(
                    'id' => 67,
                    'name' => 'AccountCreationEmail',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => '2020-01-16 10:27:28',
                ),
            53 =>
                array(
                    'id' => 68,
                    'name' => 'SendWelcomeEmail',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            54 =>
                array(
                    'id' => 69,
                    'name' => 'BroadcastSMSService',
                    'value' => 'twilio',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            55 =>
                array(
                    'id' => 70,
                    'name' => 'BroadcastMailSandbox',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            56 =>
                array(
                    'id' => 71,
                    'name' => 'BroadcastClubhouseSandbox',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            57 =>
                array(
                    'id' => 72,
                    'name' => 'BroadcastClubhouseNotify',
                    'value' => 'false',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            58 =>
                array(
                    'id' => 73,
                    'name' => 'TwilioAccountSID',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            59 =>
                array(
                    'id' => 74,
                    'name' => 'TwilioAuthToken',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            60 =>
                array(
                    'id' => 75,
                    'name' => 'TwilioServiceId',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            61 =>
                array(
                    'id' => 76,
                    'name' => 'TwilioStatusCallbackUrl',
                    'value' => '',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            62 =>
                array(
                    'id' => 77,
                    'name' => 'AdminEmail',
                    'value' => 'ranger-tech-ninjas@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            63 =>
                array(
                    'id' => 78,
                    'name' => 'GeneralSupportEmail',
                    'value' => 'rangers@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            64 =>
                array(
                    'id' => 79,
                    'name' => 'PersonnelEmail',
                    'value' => 'ranger-personnel@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            65 =>
                array(
                    'id' => 80,
                    'name' => 'VCEmail',
                    'value' => 'ranger-vc-list@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            66 =>
                array(
                    'id' => 81,
                    'name' => 'ShirtShortSleeveHoursThreshold',
                    'value' => '18',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            67 =>
                array(
                    'id' => 82,
                    'name' => 'ShirtLongSleeveHoursThreshold',
                    'value' => '30',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            68 =>
                array(
                    'id' => 83,
                    'name' => 'TrainingAcamedyEmail',
                    'value' => 'ranger-trainingacademy-list@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            70 =>
                array(
                    'id' => 85,
                    'name' => 'TrainingAcademyEmail',
                    'value' => 'ranger-trainingacademy-list@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            71 =>
                array(
                    'id' => 86,
                    'name' => 'TAS_Pickup_Locations',
                    'value' => 'July 28 - August 7:  Gerlach Office 08:00 to 18:00
August 8 - 16: Gerlach Office 08:00 to 00:00 (midnight)
August 17 - 18: On Playa Box Office 08:00 to 18:00, 21:00 to 00:00 (closed briefly from 18:00 to 21:00)
August 19 - 31: Box Office 24 hours starting 8/19 @ 08:00 to 9/1 @ 12:00

If you are arriving by the Burner Express Bus or Air, you will need to pick up your SC at the Burner Express Window before boarding the bus or plane.',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            72 =>
                array(
                    'id' => 88,
                    'name' => 'TAS_WAPDateRange',
                    'value' => '3-24',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            73 =>
                array(
                    'id' => 89,
                    'name' => 'BurnWeekendSignUpMotivationPeriod',
                    'value' => '2019-08-30 18:00/2019-09-01 18:00',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            75 =>
                array(
                    'id' => 91,
                    'name' => 'PhotoUploadEnable',
                    'value' => 'true',
                    'created_at' => NULL,
                    'updated_at' => '2020-02-13 11:18:35',
                ),
            76 =>
                array(
                    'id' => 92,
                    'name' => 'DailyReportEmail',
                    'value' => 'frankenstein@example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            77 =>
                array(
                    'id' => 93,
                    'name' => 'ThankYouCardsHash',
                    'value' => '3afdda2c098fc8346a9f2e0ba06ec9c6188411cf78cc7934cfd8b666ce632f55',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            78 =>
                array(
                    'id' => 94,
                    'name' => 'RangerFeedbackFormUrl',
                    'value' => 'https://example.com',
                    'created_at' => NULL,
                    'updated_at' => NULL,
                ),
            79 =>
                array(
                    'id' => 95,
                    'name' => 'MentorEmail',
                    'value' => 'ranger-mentor-cadre@example.com',
                    'created_at' => '2020-01-12 17:39:50',
                    'updated_at' => '2020-01-12 17:39:50',
                ),
            80 =>
                array(
                    'id' => 96,
                    'name' => 'BroadcastSMSSandbox',
                    'value' => 'true',
                    'created_at' => '2020-01-16 10:35:37',
                    'updated_at' => '2020-01-16 10:35:37',
                ),
            81 =>
                array(
                    'id' => 97,
                    'name' => 'OnboardAlphaShiftPrepLink',
                    'value' => 'https://rangers.burningman.org/2019_brrm/2019_m_alpha-shift/',
                    'created_at' => '2020-01-16 10:38:49',
                    'updated_at' => '2020-01-16 10:38:49',
                ),
            82 =>
                array(
                    'id' => 98,
                    'name' => 'PhotoStorage',
                    'value' => 'photos-s3',
                    'created_at' => '2020-02-01 22:40:52',
                    'updated_at' => '2020-02-01 22:40:52',
                ),
            83 =>
                array(
                    'id' => 99,
                    'name' => 'PhotoAnalysisEnabled',
                    'value' => 'false',
                    'created_at' => '2020-02-02 09:31:41',
                    'updated_at' => '2020-02-02 09:31:41',
                ),
            84 =>
                array(
                    'id' => 100,
                    'name' => 'PhotoRekognitionAccessKey',
                    'value' => '',
                    'created_at' => '2020-02-02 09:31:48',
                    'updated_at' => '2020-02-02 09:31:48',
                ),
            85 =>
                array(
                    'id' => 101,
                    'name' => 'PhotoRekognitionAccessSecret',
                    'value' => '',
                    'created_at' => '2020-02-02 09:31:59',
                    'updated_at' => '2020-02-02 09:31:59',
                ),
            86 =>
                array(
                    'id' => 102,
                    'name' => 'PhotoPendingNotifyEmail',
                    'value' => '',
                    'created_at' => '2020-02-16 13:04:29',
                    'updated_at' => '2020-02-27 08:32:40',
                ),
            87 =>
                array(
                    'id' => 103,
                    'name' => 'OnlineTrainingDisabledAllowSignups',
                    'value' => 'true',
                    'created_at' => '2020-03-10 14:56:15',
                    'updated_at' => '2020-03-27 07:27:02',
                ),
            88 =>
                array(
                    'id' => 104,
                    'name' => 'OnlineTrainingEnabled',
                    'value' => 'true',
                    'created_at' => '2020-03-10 14:56:19',
                    'updated_at' => '2020-03-13 15:10:40',
                ),
            89 =>
                array(
                    'id' => 105,
                    'name' => 'OnlineTrainingUrl',
                    'value' => 'https://learning.burningman.org',
                    'created_at' => '2020-03-10 14:56:27',
                    'updated_at' => '2020-03-10 14:56:27',
                ),
            90 =>
                array(
                    'id' => 106,
                    'name' => 'DoceboClientId',
                    'value' => '',
                    'created_at' => '2020-03-10 14:57:04',
                    'updated_at' => '2020-03-10 14:57:04',
                ),
            91 =>
                array(
                    'id' => 107,
                    'name' => 'DoceboClientSecret',
                    'value' => '',
                    'created_at' => '2020-03-10 14:57:10',
                    'updated_at' => '2020-03-10 14:57:10',
                ),
            92 =>
                array(
                    'id' => 108,
                    'name' => 'DoceboDomain',
                    'value' => 'learning.burningman.org',
                    'created_at' => '2020-03-10 14:57:16',
                    'updated_at' => '2020-03-10 14:57:16',
                ),
            93 =>
                array(
                    'id' => 109,
                    'name' => 'DoceboPassword',
                    'value' => '',
                    'created_at' => '2020-03-10 14:57:29',
                    'updated_at' => '2020-03-10 14:57:29',
                ),
            94 =>
                array(
                    'id' => 110,
                    'name' => 'DoceboUsername',
                    'value' => '',
                    'created_at' => '2020-03-10 14:57:43',
                    'updated_at' => '2020-03-10 14:57:43',
                ),
            95 =>
                array(
                    'id' => 111,
                    'name' => 'DoceboFullCourseId',
                    'value' => '',
                    'created_at' => '2020-03-13 14:57:04',
                    'updated_at' => '2020-03-13 14:57:04',
                ),
            96 =>
                array(
                    'id' => 112,
                    'name' => 'DoceboHalfCourseId',
                    'value' => '',
                    'created_at' => '2020-03-13 14:57:10',
                    'updated_at' => '2020-03-13 14:57:10',
                ),
        ));


    }
}