<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('setting', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 128)->unique();
            $table->text('value', 65535)->nullable();
            $table->enum('type', [ 'bool', 'string', 'integer', 'json' ]);
            $table->text('description', 65535)->nullable();
            $table->text('options', 65535)->nullable();
            $table->boolean('environment_only')->default(0);
            $table->timestamps();
        });

        $this->seedSettings();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('setting');
    }

    public function seedSettings()
    {
        \DB::table('setting')->insert([ 'name' => 'DeploymentEnvironment', 'type' => 'string', 'value' => '' ]);
        \DB::table('setting')->insert([
            'name' => 'OnPlaya',
            'type' => 'bool',
            'value' => 'false',
            'description' => "Enable server relocated to playa notification (not implement currently)"
        ]);

        \DB::table('setting')->insert([
            'name' => 'AllowSignupsWithoutPhoto',
            'type' => 'bool',
            'value' => 'false',
            'description' => 'Allow shift signups without requiring an approved photo'
        ]);

        \DB::table('setting')->insert([ 'name' => 'ReadOnly', 'type' => 'bool', 'value' => 'false' ]);

        \DB::table('setting')->insert([ 'name' => 'SiteNotice', 'type' => 'string', 'value' => 'Copyright 2008-2019 Black Rock City, LLC. All information contained within this website is strictly confidential.' ]);
        \DB::table('setting')->insert([ 'name' => 'SiteTitle', 'type' => 'string', 'value' => 'Black Rock Rangers Secret Clubhouse' ]);

        \DB::table('setting')->insert([
            'name' => 'PhotoSource', 'type' => 'string', 'value' => 'Lambase',
            'options' => "Lambase\nlocal\ntest\n",
            'description' => "Mugshot image location\nLambase = from the Lambase server\nlocal = from the local server\ntest = serve a dummy file\n"
        ]);
        \DB::table('setting')->insert([
            'name' => 'PhotoStoreLocally',
            'type' => 'bool', 'value' => 'false',
            'description' => 'Enable storing Lambase photos locally'
        ]);
        \DB::table('setting')->insert([
            'name' => 'LambaseStatusUrl',
            'type' => 'string',
            'value' => 'https://www.lambase.com/webservice/photo_status_rangers.cfc',
            'description' => 'Lambase status URL - should rarely change'
        ]);

        \DB::table('setting')->insert([
            'name' => 'LambaseReportUrl',
            'type' => 'string',
            'value' => 'https://www.lambase.com/webservice/photo_status_rpt_rangers.cfc',
            'description' => 'Lambase Photo Status Report API url'
        ]);

        \DB::table('setting')->insert([
            'name' => 'LambasePrintStatusUpdateUrl',
            'type' => 'string',
            'value' => 'http://www.lambase.com/webservice/print_status_rangers.cfc',
            'description' => 'Lambase Printing Status API url'
        ]);

        \DB::table('setting')->insert([
            'name' => 'LambaseImageUrl',
            'type' => 'string',
            'value' => 'https://www.lambase.com/lam_photos/rangers',
            'description' => 'Lambase Image URL'
        ]);
        \DB::table('setting')->insert([ 'name' => 'LambaseJumpinUrl', 'type' => 'string', 'value' => 'https://www.lambase.com/jumpin/rangers.cfm' ]);

        \DB::table('setting')->insert([ 'name' => 'TimesheetCorrectionEnable', 'type' => 'bool', 'value' => 'true', 'description' => "Enable Timesheet Corrections" ]);
        \DB::table('setting')->insert([ 'name' => 'TimesheetCorrectionYear', 'type' => 'string', 'value' => '2018', 'description' => 'Timesheet Corrections for year' ]);

        \DB::table('setting')->insert([
                'name' => 'ClubhouseSuggestionUrlTemplate',
                'type' => 'string',
                'value' => 'https://docs.google.com/forms/d/1Rox154ty2thJThS5KE-zapNKi4Rcsjgg094YyoTqeWQ/viewform?entry.561095571={callsign}&entry.1642048126={email}',
                'description' => 'Clubhouse Suggestion submission form URL'
            ]);

        \DB::table('setting')->insert([
            'name' => 'RangerPoliciesUrl',
            'type' => 'string',
            'value' => 'https://drive.google.com/drive/u/1/folders/0B9mqynALmDHDQ2VvaFB3SnVvMTg?ogsrc=32',
            'description' => 'Rnager Policy Document URL'
        ]);

        \DB::table('setting')->insert([
            'name' => 'JoiningRangerSpecialTeamsUrl',
            'type' => 'string',
            'value' => 'https://docs.google.com/document/d/1xEVnm1SdcyvLnsUYwL5v_WxO1zy3yhMkAmbXIU_0yqc',
            'description' => "How To Join Ranger Special Teams",
            ]);

        \DB::table('setting')->insert([
            'name' => 'PNVWaitList',
            'type' => 'bool',
            'value' => 'false',
            'description' => 'Enable Prospecitve Waitlisting'
        ]);

        \DB::table('setting')->insert([
            'name' => 'ManualReviewLinkEnable',
            'type' => 'bool',
            'value' => 'false',
            'description' => 'Show the Manual Review Link'
        ]);

        \DB::table('setting')->insert([
            'name' => 'ManualReviewDisabledAllowSignups',
            'type' => 'bool',
            'value' => 'true',
            'description' => 'Enable shift signups even if Manual Review is disabled'
        ]);
        \DB::table('setting')->insert([
            'name' => 'ManualReviewProspectiveAlphaLimit',
            'type' => 'integer',
            'value' => '177',
            'description' => "Limit the first N Alphas for Manual Review"
        ]);

        \DB::table('setting')->insert([
            'name' => 'ManualReviewGoogleFormBaseUrl',
            'type' => 'string',
            'value' => 'https://docs.google.com/forms/d/e/1FAIpQLScNcr1xZ9YHULag7rdS5-LUU_e1G1XS5kfDI85T10RVTAeZXA/viewform?entry.960989731=',
            'description' => "Manual Review URL"
        ]);

        \DB::table('setting')->insert([
            'name' => 'ManualReviewGoogleSheetId',
            'type' => 'string',
            'value' => '1T6ZoSHoQhjlcqOs0J-CQ7HoMDQd_RGs4y_SQSvOb15M',
            'description' => 'Manual Review Google Spreadsheet ID'
         ]);

        \DB::table('setting')->insert([
            'name' => 'ManualReviewAuthConfig',
            'type' => 'json',
            'value' => '',
            'description' => 'Manual Review Google Spreadsheet Authentication (usually a massive JSON blob)'
        ]);

        \DB::table('setting')->insert([
            'name' => 'SFsbxClientId',
            'type' => 'string',
            'value' => '3MVG9zZht._ZaMunO578Jy7KHxp2oy5KMiCnRBHoLOUKvk6hUB3jHcmgsnMImbo30PPXFL4p5qUSYrMdYQ_C5',
            'description' => 'Salesforce Sandbox Client ID'
        ]);

        \DB::table('setting')->insert([ 'name' => 'SFsbxClientSecret', 'type' => 'string', 'value' => '90600550470841821', 'description' => 'Salesforce Sandbox Client Secret' ]);
        \DB::table('setting')->insert([ 'name' => 'SFsbxUsername', 'type' => 'string', 'value' => 'philapi@burningman.com.dev3', 'description' => 'Salesforce Sandbox Username' ]);
        \DB::table('setting')->insert([ 'name' => 'SFsbxAuthUrl', 'type' => 'string', 'value' => 'https://test.salesforce.com/services/oauth2/token', 'description' => 'Salesforce Sandbox Authentication URL' ]);
        \DB::table('setting')->insert([ 'name' => 'SFsbxPassword', 'type' => 'string', 'value' => '*', 'description' => 'Salesforce Sandbox Password' ]);

        \DB::table('setting')->insert([ 'name' => 'SFprdClientId', 'type' => 'string', 'value' => '3MVG9rFJvQRVOvk6W.N0QISwohURI.shVyYx2vyhMlZ_39Wi9wohZYuDbY5Fuhd_0sOCRB1.jn.ijRia1F0Cd', 'description' => 'Salesforce Production Client ID' ]);
        \DB::table('setting')->insert([ 'name' => 'SFprdClientSecret', 'type' => 'string', 'value' => '7358471550048400762', 'description' => 'Salesforce Production Client Secret' ]);
        \DB::table('setting')->insert([ 'name' => 'SFprdUsername', 'type' => 'string', 'value' => 'diverdave@burningman.com', 'description' => 'Salesforce Production Username'  ]);
        \DB::table('setting')->insert([ 'name' => 'SFprdAuthUrl', 'type' => 'string', 'value' => 'https://login.salesforce.com/services/oauth2/token', 'description' => 'Salesforce Production Authentication URL' ]);
        \DB::table('setting')->insert([ 'name' => 'SFprdPassword', 'type' => 'string', 'value' => '*', 'description' => 'Salesforce Production Password'  ]);
        \DB::table('setting')->insert([ 'name' => 'SFEnableWritebacks', 'type' => 'bool', 'value' => 'false', 'description' => 'Enable Saleforce Object Update' ]);

        \DB::table('setting')->insert([ 'name' => 'TicketingPeriod', 'type' => 'string', 'value' => 'offseason', 'options' => "offseason\nannounce\nopen\nclose\n",
            'description' =>
            'Ticketing Period / Season
    offseason = past event but before ticket awards
    announce = tickets have been awarded but ticketing window is not open
    open = tickets can be claimed and TAS_Tickets, TAS_VP, TAS_WAP, TAS_WAPSO, TAS_Delivery come into play.
    closed = ticketing is closed changes are not directly allowed
    '
            ]);
        \DB::table('setting')->insert([
            'name' => 'TicketsAndStuffEnablePNV',
            'type' => 'bool',
            'value' => 'false',
            'description' => "Enable Ticketing Page for PNVs"
         ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_SubmitDate', 'type' => 'string', 'value' => '2019-07-16 23:59:00', 'description' => 'Ticketing Submission Deadline' ]);

        \DB::table('setting')->insert([ 'name' => 'TAS_Tickets', 'type' => 'string', 'value' => 'accept', 'options' => "none\nview\naccept\nfrozen\n",
            'description' => "Event Ticket Mode\nnone = not available yet\nview = ticket announcement\naccept = allow ticket submissions\nfrozen = ticketing window is closed\n"
        ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_VP', 'type' => 'string', 'value' => 'accept', 'options' => "none\nview\naccept\nfrozen\n",
            'description' => "Vehicle Pass Mode\nnone = not available yet\nview = ticket announcement\naccept = allow ticket submissions\nfrozen = ticketing window is closed\n"
        ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAP', 'type' => 'string', 'value' => 'accept', 'options' => "none\nview\naccept\nfrozen\n",
            'description' => "Work Access Pass Mode\nnone = not available yet\nview = ticket announcement\naccept = allow ticket submissions\nfrozen = ticketing window is closed\n"
        ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAPSO', 'type' => 'string', 'value' => 'accept', 'options' => "none\nview\naccept\nfrozen\n",
            'description' => "WAP SO Mode\nnone = not available yet\nview = ticket announcement\naccept = allow ticket submissions\nfrozen = ticketing window is closed\n"

        ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Delivery', 'type' => 'string', 'value' => 'accept', 'options' => "none\nview\naccept\nfrozen\n",
            'description' => "Ticket Delivery View\nnone = not available yet\nview = ticket announcement\naccept = allow ticket submissions\nfrozen = ticketing window is closed\n"
        ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAPSOMax', 'type' => 'integer', 'value' => '3',  'description' => "Max. WAP SO Count" ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_BoxOfficeOpenDate', 'type' => 'string', 'value' => '2019-08-22 12:00:00', 'description' => "Playa Box Office Opening Date" ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_DefaultWAPDate', 'type' => 'string', 'value' => '2019-08-23',  'description' => 'Default WAP Access Date' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_DefaultAlphaWAPDate', 'type' => 'string', 'value' => '2019-08-26', 'description' => 'Default Alpha WAP Access Date' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_DefaultSOWAPDate', 'type' => 'string', 'value' => '2019-08-26', 'description' => 'Default WAP SO Access Date' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Email', 'type' => 'string', 'value' => 'ranger-ticketing-stuff@burningman.org', 'description' => 'Ranger Ticketing Support Email' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_Ticket_FAQ', 'type' => 'string', 'value' => 'https://docs.google.com/document/d/1TILtNyPUygjVk9T0B7FEobwAwKOBTub-YewyMtJaIFs/edit', 'description' => 'Ticketing FAQ Link' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_WAP_FAQ', 'type' => 'string', 'value' => 'https://docs.google.com/document/d/1wuucvq017bQHP7-0uH2KlSWSaYW7CSvNN7siU11Ah7k/edit', 'description' => 'WAP FAQ Link' ]);
        \DB::table('setting')->insert([ 'name' => 'TAS_VP_FAQ', 'type' => 'string', 'value' => 'https://docs.google.com/document/d/1KPBD_qdyBkdDnlaVBTAVX8-U3WWcSXa-4_Kf48PbOCM/edit', 'description' => 'Vehicle Pass FAQ Link']);
        \DB::table('setting')->insert([ 'name' => 'TAS_Alpha_FAQ', 'type' => 'string', 'value' => 'https://docs.google.com/document/d/1yyIAUqP4OdjGTZeOqy1PxE_1Hynhkh0cFzALQkTM-ds/edit', 'description' => 'Alpha WAP FAQ Link' ]);
        \DB::table('setting')->insert([ 'name' => 'TicketVendorName', 'type' => 'string', 'value' => 'ShowClix', 'description' => "Ticketing Vendor Name" ]);
        \DB::table('setting')->insert([ 'name' => 'TicketVendorEmail', 'type' => 'string', 'value' => 'support@showclix.com', 'description' => "Ticketing Vendor Support Email" ]);

        // If any are not set, no message will be displayed
        \DB::table('setting')->insert([ 'name' => 'RpTicketThreshold', 'type' => 'string', 'value' => '19', 'description' => 'Credit threshold for reduced price ticket' ]);
        \DB::table('setting')->insert([ 'name' => 'ScTicketThreshold', 'type' => 'string', 'value' => '38', 'description' => 'Credit threshold for staff credential' ]);
        \DB::table('setting')->insert([ 'name' => 'YrTicketThreshold', 'type' => 'string', 'value' => '2019', 'description' => 'Threshold year to earn credits' ]);

        \DB::table('setting')->insert([ 'name' => 'MealInfoAvailable', 'type' => 'bool', 'value' => 'false', 'description' => "True if meal information is available."]);
        \DB::table('setting')->insert([ 'name' => 'MealDates', 'type' => 'string', 'value' => 'Pre Event is Tue 8/8 (dinner) - Sun 8/27; During Event is Mon 8/28 - Mon 9/4; Post Event is Tue 9/5 - Sat 9/9 (lunch)' ]);

        \DB::table('setting')->insert([ 'name' => 'RadioInfoAvailable', 'type' => 'bool', 'value' => 'false', 'description' => 'True if radio information has been uploaded.']);
        \DB::table('setting')->insert([ 'name' => 'RadioCheckoutFormUrl', 'type' => 'string', 'value' => 'https://drive.google.com/open?id=1p4EerIDN6ZRxcWkI7tDFGuMf6SgL1LTQ' ]);

        \DB::table('setting')->insert([ 'name' => 'MotorpoolPolicyEnable', 'type' => 'bool', 'value' => 'false', 'description' => "Enable Motorpool Policy Page"]);

        \DB::table('setting')->insert([ 'name' => 'TrainingSignupFromEmail',
            'type' => 'string',
            'value' => 'do-no-reply@burningman.org',
            'description' => 'From email  address for training sign up messages'
        ]);
        \DB::table('setting')->insert([ 'name' => 'ShiftSignupFromEmail',
            'type' => 'string',
            'value' => 'do-not-reply@burningman.org',
            'description' => 'From email  address for shift sign up messages'
        ]);

        \DB::table('setting')->insert([ 'name' => 'TrainingFullEmail', 'type' => 'string', 'value' => 'ranger-trainingacademy-list@burningman.org', 'description' => 'Email address to alert when training session is full.' ]);
        \DB::table('setting')->insert([ 'name' => 'AccountCreationEmail', 'type' => 'string', 'value' => 'safetyphil@burningman.org', 'description' => 'Alert email address when accounts register' ]);
        \DB::table('setting')->insert([ 'name' => 'SendWelcomeEmail', 'type' => 'bool', 'value' => 'false', 'description' => "Enable Welcome email when new account created" ]);

        \DB::table('setting')->insert([ 'name' => 'BroadcastSMSService', 'type' => 'string', 'value' => 'twilio', 'options' => "twilio\nsandbox\n", 'description' => "Ranger Broadcast SMS Service\ntwilio = send SMS to Twilio\nsandbox = developer mode, no SMS sent\n" ]);

        \DB::table('setting')->insert([ 'name' => 'BroadcastMailSandbox', 'type' => 'bool', 'value' => 'false', 'description' => 'Enable RBS sandbox email mode' ]);
        \DB::table('setting')->insert([ 'name' => 'BroadcastClubhouseSandbox', 'type' => 'bool', 'value' => 'false', 'description' => 'Enable RBS Clubhouse Message sandbox mode (Clubhouse messages not created)']);

        \DB::table('setting')->insert([ 'name' => 'BroadcastClubhouseNotify', 'type' => 'bool', 'value' => 'false', 'description' => 'Enable RBS notification of new Clubhouse messages' ]);
        \DB::table('setting')->insert([ 'name' => 'TwilioAccountSID', 'type' => 'string', 'value' => '', 'description' => 'Twilio Account SID' ]);
        \DB::table('setting')->insert([ 'name' => 'TwilioAuthToken', 'type' => 'string', 'value' => '', 'description' => 'Twilio Authenication Token' ]);
        \DB::table('setting')->insert([ 'name' => 'TwilioServiceId', 'type' => 'string', 'value' => '', 'description' => 'Twilio Service ID of SMS Channel' ]);

        \DB::table('setting')->insert([ 'name' => 'TwilioStatusCallbackUrl', 'type' => 'string', 'value' => '',
            'description' => 'Twilio Status Callback URL\nTwilio can provide status callback for each message. Unknown if the
playa Internet link is reliable enough, or if the clubhouse can handle
a rapid stream of status updates.
https://ranger-clubhouse.burningman.org/?DMSc=broadcast&DMSm=smsStatusCallback
'
           ]);

        \DB::table('setting')->insert([ 'name' => 'AdminEmail', 'type' => 'string', 'value' => 'ranger-tech-ninjas@burningman.org', 'description' => 'Ranger Tech Team Contact' ]);
        \DB::table('setting')->insert([ 'name' => 'GeneralSupportEmail', 'type' => 'string', 'value' => 'rangers@burningman.org', 'description' => 'General Ranger Email Contact' ]);
        \DB::table('setting')->insert([ 'name' => 'PersonnelEmail', 'type' => 'string', 'value' => 'ranger-personnel@burningman.org', 'description' => 'Ranger Personnel Email Contact' ]);
        \DB::table('setting')->insert([ 'name' => 'VCEmail', 'type' => 'string', 'value' => 'ranger-vc-list@burningman.org', 'description' => 'Ranger Volunteer Coordinator Contact' ]);
    }
}
