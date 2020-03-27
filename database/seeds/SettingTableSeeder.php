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
        \DB::table('setting')->insert([ 'name' => 'AccountCreationEmail',  'value' => 'safetyphil@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'AdminEmail',  'value' => 'ranger-tech-ninjas@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'AllowSignupsWithoutPhoto', 'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'BroadcastClubhouseNotify',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'BroadcastClubhouseSandbox',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'BroadcastMailSandbox',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'BroadcastSMSService',  'value' => 'twilio' ]);
        \DB::table('setting')->insert([ 'name' => 'GeneralSupportEmail',  'value' => 'rangers@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'JoiningRangerSpecialTeamsUrl', 'value' => 'https://docs.google.com/document/d/1xEVnm1SdcyvLnsUYwL5v_WxO1zy3yhMkAmbXIU_0yqc' ]);
        \DB::table('setting')->insert([ 'name' => 'LambaseImageUrl', 'value' => 'https://www.lambase.com/lam_photos/rangers' ]);
        \DB::table('setting')->insert([ 'name' => 'LambaseJumpinUrl',  'value' => 'https://www.lambase.com/jumpin/rangers.cfm' ]);
        \DB::table('setting')->insert([ 'name' => 'LambasePrintStatusUpdateUrl', 'value' => 'http://www.lambase.com/webservice/print_status_rangers.cfc' ]);
        \DB::table('setting')->insert([ 'name' => 'LambaseReportUrl', 'value' => 'https://www.lambase.com/webservice/photo_status_rpt_rangers.cfc' ]);
        \DB::table('setting')->insert([ 'name' => 'LambaseStatusUrl', 'value' => 'https://www.lambase.com/webservice/photo_status_rangers.cfc' ]);
        \DB::table('setting')->insert([ 'name' => 'ManualReviewDisabledAllowSignups', 'value' => 'true' ]);
        \DB::table('setting')->insert([ 'name' => 'ManualReviewLinkEnable', 'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'ManualReviewProspectiveAlphaLimit', 'value' => '177' ]);
        \DB::table('setting')->insert([ 'name' => 'MealDates',  'value' => 'Pre Event is Tue 8/8 (dinner) - Sun 8/27; During Event is Mon 8/28 - Mon 9/4; Post Event is Tue 9/5 - Sat 9/9 (lunch)' ]);
        \DB::table('setting')->insert([ 'name' => 'MealInfoAvailable',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'MotorpoolPolicyEnable',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'OnPlaya', 'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'PersonnelEmail',  'value' => 'ranger-personnel@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'PhotoStoreLocally', 'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'PNVWaitList', 'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'RadioCheckoutFormUrl',  'value' => 'https://drive.google.com/open?id=1p4EerIDN6ZRxcWkI7tDFGuMf6SgL1LTQ' ]);
        \DB::table('setting')->insert([ 'name' => 'RadioInfoAvailable',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'RangerPoliciesUrl', 'value' => 'https://drive.google.com/drive/u/1/folders/0B9mqynALmDHDQ2VvaFB3SnVvMTg?ogsrc=32' ]);
        \DB::table('setting')->insert([ 'name' => 'ReadOnly',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'RpTicketThreshold',  'value' => '19' ]);
        \DB::table('setting')->insert([ 'name' => 'ScTicketThreshold',  'value' => '38' ]);
        \DB::table('setting')->insert([ 'name' => 'SendWelcomeEmail',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'SFEnableWritebacks',  'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'ShiftSignupFromEmail', 'value' => 'do-not-reply@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Alpha_FAQ',  'value' => 'https://docs.google.com/document/d/1yyIAUqP4OdjGTZeOqy1PxE_1Hynhkh0cFzALQkTM-ds/edit' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_BoxOfficeOpenDate',  'value' => '2019-08-22 12:00:00' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_DefaultAlphaWAPDate',  'value' => '2019-08-26' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_DefaultSOWAPDate',  'value' => '2019-08-26' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_DefaultWAPDate',  'value' => '2019-08-23' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Delivery',  'value' => 'accept' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Email',  'value' => 'ranger-ticketing-stuff@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_SubmitDate',  'value' => '2019-07-16 23:59:00'  ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Ticket_FAQ',  'value' => 'https://docs.google.com/document/d/1TILtNyPUygjVk9T0B7FEobwAwKOBTub-YewyMtJaIFs/edit' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Tickets',  'value' => 'accept' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_VP_FAQ',  'value' => 'https://docs.google.com/document/d/1KPBD_qdyBkdDnlaVBTAVX8-U3WWcSXa-4_Kf48PbOCM/edit' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_VP',  'value' => 'accept' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAP_FAQ',  'value' => 'https://docs.google.com/document/d/1wuucvq017bQHP7-0uH2KlSWSaYW7CSvNN7siU11Ah7k/edit' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAP',  'value' => 'accept']);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAPSO',  'value' => 'accept' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAPSOMax',  'value' => '3' ]);
        \DB::table('setting')->insert([ 'name' => 'TicketingPeriod',  'value' => 'offseason' ]);
        \DB::table('setting')->insert([ 'name' => 'TicketsAndStuffEnablePNV', 'value' => 'false' ]);
        \DB::table('setting')->insert([ 'name' => 'TimesheetCorrectionEnable',  'value' => 'true'  ]);
        \DB::table('setting')->insert([ 'name' => 'TimesheetCorrectionYear',  'value' => '2018' ]);
        \DB::table('setting')->insert([ 'name' => 'TrainingSignupFromEmail', 'value' => 'do-no-reply@burningman.org' ]);
        \DB::table('setting')->insert([ 'name' => 'VCEmail',  'value' => 'ranger-vc-list@burningman.org' ]);
    }
}
