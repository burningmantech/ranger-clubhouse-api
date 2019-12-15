<?php

namespace App\Models;

use App\Models\ApiModel;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

class Setting extends ApiModel
{
    protected $table = 'setting';
    public $timestamps = true;

    // Allow all fields to be filled.
    protected $guarded = [];

    protected $primaryKey = 'name';
    public $incrementing = false;

    protected $rules = [
        'name'  => 'required|string',
        'value' => 'required|string|nullable'
    ];

    public $appends = [
        'type',
        'description',
        'is_credential',
        'options'
    ];

    public static $cache = [];

    /*
     * Each setting must be described in the table below.
     *
     * The definitions are:
     * description: single line detail on what the setting is
     * type: the value type (bool,string,json,email,date,datetime,integer,float)
     * is_credential (optional) - set true if the setting is a credential and should not be included in a redact database dump
     * options: array of possible options format is [ 'option', 'description' ]
     */


    const DESCRIPTIONS = [
        'AccountCreationEmail' => [
            'description' => 'Alert email address when accounts register',
            'type' => 'email',
        ],

        'AdminEmail' => [
            'description' => 'Ranger Tech Team Email Address',
            'type' => 'email',
        ],

        'AllowSignupsWithoutPhoto' => [
            'description' => 'Allow shift signups without requiring an approved photo',
            'type' => 'bool',
        ],

        'BmidTestToken' => [
            'description' => 'BMID test token to trigger Lambase bug',
            'type' => 'string',
        ],

        'BroadcastClubhouseNotify' => [
            'description' => 'Enable RBS notification of new Clubhouse messages',
            'type' => 'bool',
        ],

        'BroadcastClubhouseSandbox' => [
            'description' => 'Enable RBS Clubhouse Message sandbox mode (Clubhouse messages not created)',
            'type' => 'bool',
        ],

        'BroadcastMailSandbox' => [
            'description' => 'Enable RBS sandbox email mode',
            'type' => 'bool',
        ],

        'BroadcastSMSService' => [
            'description' => 'Ranger Broadcast SMS Service',
            'type' => 'string',
            'options' => [
                [ 'twilio', 'deliver SMS messages via Twilio' ],
                [ 'sandbox', 'No SMS sent - developer mode' ],
            ]
        ],

        'BroadcastSMSSandbox' => [
            'description' => 'Sandbox SMS messages',
            'type' => 'bool',
        ],

        'BurnWeekendSignUpMotivationPeriod' => [
            'description' => 'Nag the person to sign up for Burn Weekend shifts. Format: YYYY-MM-DD HH:MM/YYYY-MM-DD HH:MM',
            'type' => 'string',
        ],

        'DailyReportEmail' => [
            'description' => 'Email address to send the Clubhouse Daily Report',
            'type' => 'email',
        ],

        'GeneralSupportEmail' => [
            'description' => 'General Ranger Email Address',
            'type' => 'email',
        ],

        'JoiningRangerSpecialTeamsUrl' => [
            'description' => 'How To Join Ranger Special Teams Document URL',
            'type' => 'string',
        ],

        'LambaseImageUrl' => [
            'description' => 'Lambase Image URL',
            'type' => 'string',
        ],

        'LambaseJumpinUrl' => [
            'description' => 'Lambase Upload and Login link',
            'type' => 'string',
        ],

        'LambasePrintStatusUpdateUrl' => [
            'description' => 'Lambase Printing Status API url',
            'type' => 'string',
        ],

        'LambaseReportUrl' => [
            'description' => 'Lambase Photo Status Report API url',
            'type' => 'string',
        ],

        'LambaseStatusUrl' => [
            'description' => 'Lambase status URL - should rarely change',
            'type' => 'string',
        ],

        'MaintenanceToken' => [
            'description' => 'Security Token used to initiate nightly maintenance tasks',
            'type' => 'string',
        ],

        'ManualReviewAuthConfig' => [
            'description' => 'Manual Review Google Spreadsheet Authentication (a massive JSON blob)',
            'type' => 'json',
            'is_credential' => true,
        ],

        'ManualReviewDisabledAllowSignups' => [
            'description' => 'Enable shift signups even if Manual Review is disabled',
            'type' => 'bool',
        ],

        'ManualReviewGoogleFormBaseUrl' => [
            'description' => 'Manual Review URL',
            'type' => 'string',
        ],

        'ManualReviewGoogleSheetId' => [
            'description' => 'Manual Review Google Spreadsheet ID',
            'type' => 'string',
        ],

        'ManualReviewLinkEnable' => [
            'description' => 'Show the Manual Review Link',
            'type' => 'bool',
        ],

        'ManualReviewProspectiveAlphaLimit' => [
            'description' => 'Limit the first N Alphas for Manual Review.',
            'type' => 'integer',
        ],

        'MealDates' => [
            'description' => 'Commissary dates and hours',
            'type' => 'string',
        ],

        'MealInfoAvailable' => [
            'description' => 'True if meal information is available.',
            'type' => 'bool',
        ],

        'MotorpoolPolicyEnable' => [
            'description' => 'Enable Motorpool Policy Page',
            'type' => 'bool',
        ],

        'OnPlaya' => [
            'description' => 'Enable server relocated to playa notification (unsupported currently)',
            'type' => 'bool',
        ],

        'PNVWaitList' => [
            'description' => 'Enable Prospecitve Waitlisting',
            'type' => 'bool',
        ],

        'PersonnelEmail' => [
            'description' => 'Ranger Personnel Email Address',
            'type' => 'email',
        ],

        'PhotoSource' => [
            'description' => 'Mugshot image location',
            'type' => 'string',
            'options' => [
                [ 'Lambase', 'from the Lambase server' ],
                [ 'local', 'from the local server' ],
                [ 'test', 'serve a dummy image file (for testing only)' ],
            ]
        ],

        'PhotoStoreLocally' => [
            'description' => 'Enable storing Lambase photos locally',
            'type' => 'bool',
        ],

        'PhotoUploadEnable' => [
            'description' => 'Enable Photo Uploading',
            'type' => 'bool',
        ],

        'RadioCheckoutFormUrl' => [
            'description' => 'Radio Checkout Form URL',
            'type' => 'string',
        ],

        'RadioInfoAvailable' => [
            'description' => 'True if radio information has been uploaded.',
            'type' => 'bool',
        ],

        'RangerFeedbackFormUrl' => [
            'description' => 'Ranger Feedback Form URL',
            'type' => 'string',
        ],

        'RangerPoliciesUrl' => [
            'description' => 'Rnager Policy Document URL',
            'type' => 'string',
        ],

        'ReadOnly' => [
            'description' => 'Set the Clubhouse into Read Only mode (unsupported currently)',
            'type' => 'bool',
        ],

        'RpTicketThreshold' => [
            'description' => 'Credit threshold for reduced price ticket',
            'type' => 'float',
        ],

        'SFEnableWritebacks' => [
            'description' => 'Enable Saleforce Object Update',
            'type' => 'bool',
        ],

        'SFprdAuthUrl' => [
            'description' => 'Salesforce Production Authentication URL',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFprdClientId' => [
            'description' => 'Salesforce Production Client ID',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFprdClientSecret' => [
            'description' => 'Salesforce Production Client Secret',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFprdPassword' => [
            'description' => 'Salesforce Production Password',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFprdUsername' => [
            'description' => 'Salesforce Production Username',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFsbxAuthUrl' => [
            'description' => 'Salesforce Sandbox Authentication URL',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFsbxClientId' => [
            'description' => 'Salesforce Sandbox Client ID',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFsbxClientSecret' => [
            'description' => 'Salesforce Sandbox Client Secret',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFsbxPassword' => [
            'description' => 'Salesforce Sandbox Password',
            'type' => 'string',
            'is_credential' => true,
        ],

        'SFsbxUsername' => [
            'description' => 'Salesforce Sandbox Username',
            'type' => 'string',
            'is_credential' => true,
        ],

        'ScTicketThreshold' => [
            'description' => 'Credit threshold for staff credential',
            'type' => 'float',
        ],

        'SendWelcomeEmail' => [
            'description' => 'Enable Welcome email when an account is created',
            'type' => 'bool',
        ],

        'ShiftSignupFromEmail' => [
            'description' => 'From email  address for shift sign up messages',
            'type' => 'email',
        ],

        'ShirtLongSleeveHoursThreshold' => [
            'description' => 'Hour threshold to earn a long sleeve shirt',
            'type' => 'integer',
        ],

        'ShirtShortSleeveHoursThreshold' => [
            'description' => 'Hour threshold to earn a short sleeve shirt/t-shirt',
            'type' => 'integer',
        ],

        'TAS_Alpha_FAQ' => [
            'description' => 'Alpha WAP FAQ Link',
            'type' => 'string',
        ],

        'TAS_BoxOfficeOpenDate' => [
            'description' => 'Playa Box Office Opening date and time',
            'type' => 'datetime',
        ],

        'TAS_DefaultAlphaWAPDate' => [
            'description' => 'Default Alpha WAP Access Date',
            'type' => 'date',
        ],

        'TAS_DefaultSOWAPDate' => [
            'description' => 'Default WAP SO Access Date',
            'type' => 'date',
        ],

        'TAS_DefaultWAPDate' => [
            'description' => 'Default WAP Access Date',
            'type' => 'date',
        ],

        'TAS_Delivery' => [
            'description' => 'Ticket Delivery View',
            'type' => 'string',
            'options' => [
                [ 'none', 'not available yet' ],
                [ 'view', 'ticket announcement' ],
                [ 'accept', 'allow ticket submissions' ],
                [ 'frozen', 'ticket window is closed' ],
            ]
        ],

        'TAS_Email' => [
            'description' => 'Ranger Ticketing Support Email',
            'type' => 'email',
        ],

        'TAS_Pickup_Locations' => [
            'description' => 'Locations w/hours to pickup staff credentials and will-call items. Shown on the ticketing page',
            'type' => 'string',
        ],

        'TAS_SubmitDate' => [
            'description' => 'Ticketing Submission Deadline',
            'type' => 'string',
        ],

        'TAS_Ticket_FAQ' => [
            'description' => 'Ticketing FAQ Link',
            'type' => 'string',
        ],

        'TAS_Tickets' => [
            'description' => 'Event Ticket Mode',
            'type' => 'string',
            'options' => [
                [ 'none', 'not available yet' ],
                [ 'view', 'ticket announcement' ],
                [ 'accept', 'allow ticket submissions' ],
                [ 'frozen', 'ticket window is closed' ],
            ]
        ],

        'TAS_VP' => [
            'description' => 'Vehicle Pass Mode',
            'type' => 'string',
            'options' => [
                [ 'none', 'not available yet' ],
                [ 'view', 'ticket announcement' ],
                [ 'accept', 'allow ticket submissions' ],
                [ 'frozen', 'ticket window is closed' ],
            ]
        ],

        'TAS_VP_FAQ' => [
            'description' => 'Vehicle Pass FAQ Link',
            'type' => 'string',
        ],

        'TAS_WAP' => [
            'description' => 'Work Access Pass Mode',
            'type' => 'string',
            'options' => [
                [ 'none', 'not available yet' ],
                [ 'view', 'ticket announcement' ],
                [ 'accept', 'allow ticket submissions' ],
                [ 'frozen', 'ticket window is closed' ],
            ]
        ],

        'TAS_WAPDateRange' => [
            'description' => 'WAP allowable date range. Format: MM/DD-MM/DD',
            'type' => 'string',
        ],

        'TAS_WAPSO' => [
            'description' => 'WAP SO Mode',
            'type' => 'string',
            'options' => [
                [ 'none', 'not available yet' ],
                [ 'view', 'ticket announcement' ],
                [ 'accept', 'allow ticket submissions' ],
                [ 'frozen', 'ticket window is closed' ],
            ]
        ],

        'TAS_WAPSOMax' => [
            'description' => 'Max. WAP SO Count',
            'type' => 'integer',
        ],

        'TAS_WAP_FAQ' => [
            'description' => 'WAP FAQ Link',
            'type' => 'string',
        ],

        'ThankYouCardsHash' => [
            'description' => 'Thank You card page password. SHA-256 encoded.',
            'type' => 'string',
            'is_credential' => true,
        ],

        'TicketVendorEmail' => [
            'description' => 'Ticketing Vendor Support Email',
            'type' => 'email',
        ],

        'TicketVendorName' => [
            'description' => 'Ticketing Vendor Name',
            'type' => 'string',
        ],

        'TicketingPeriod' => [
            'description' => 'Ticketing Period / Season',
            'type' => 'string',
            'options' => [
                [ 'offseason', 'Post-event' ],
                [ 'announce', 'tickets have been awarded but ticketing window is not open' ],
                [ 'open', 'tickets can be claimed and TAS_Tickets, TAS_VP, TAS_WAP, TAS_WAPSO, TAS_Delivery come into play' ],
                [ 'closed', 'ticketing is closed changes are not directly allowed' ],
            ]
        ],

        'TicketsAndStuffEnablePNV' => [
            'description' => 'Enable Ticketing Page for PNVs',
            'type' => 'bool',
        ],

        'TimesheetCorrectionEnable' => [
            'description' => 'Allow users to submit Timesheet Corrections',
            'type' => 'bool',
        ],

        'TimesheetCorrectionYear' => [
            'description' => 'Timesheet Corrections for year',
            'type' => 'string',
        ],

        'TrainingAcademyEmail' => [
            'description' => 'Training Academy Email',
            'type' => 'email',
        ],

        'TrainingSignupFromEmail' => [
            'description' => 'From email address for training sign up messages',
            'type' => 'email',
        ],

        'TwilioAccountSID' => [
            'description' => 'Twilio Account SID',
            'type' => 'string',
            'is_credential' => true,
        ],

        'TwilioAuthToken' => [
            'description' => 'Twilio Authentication Token',
            'type' => 'string',
            'is_credential' => true,
        ],

        'TwilioServiceId' => [
            'description' => 'Twilio Service ID of SMS Channel',
            'type' => 'string',
            'is_credential' => true,
        ],

        'TwilioStatusCallbackUrl' => [
            'description' => 'Twilio Status Callback URL (not implemented currently)',
            'type' => 'string',
        ],

        'VCEmail' => [
            'description' => 'Ranger Volunteer Coordinator Address',
            'type' => 'email',
        ],

        'YrTicketThreshold' => [
            'description' => 'Threshold year to earn credits',
            'type' => 'float',
        ],
    ];

    /*
     * Find a setting. Must be defined in the DESCRIPTIONS table
     */

    public static function find($name)
    {
        $desc = self::DESCRIPTIONS[$name] ?? null;

        if (!$desc) {
            // Setting is not defined.
            return null;
        }

        // Lookup the value
        return Setting::where('name', $name)->firstOrNew([ 'name' => $name ]);
    }

    public static function findOrFail($name)
    {
        $row = self::find($name);

        if ($row) {
            return $row;
        }

        throw (new ModelNotFoundException)->setModel(Setting::class, $name);
    }

    public static function findAll()
    {
        $rows = Setting::all()->keyBy('name');

        $settings = collect([]);
        foreach (self::DESCRIPTIONS as $name => $desc) {
            $settings[] = $rows[$name] ?? new Setting([ 'name' => $name ]);
        }

        return $settings->sortBy('name')->values();
    }

    public static function get($name)
    {
        if (is_array($name)) {
            $rows = self::select('name', 'value')->whereIn('name', $name)->get()->keyBy('name');
            $settings = [];
            foreach ($name as $setting) {
                $row = $rows[$setting] ?? null;
                $desc = self::DESCRIPTIONS[$setting] ?? null;
                if (!$desc) {
                    throw new \InvalidArgumentException("'$setting' is an unknown setting.");
                }
                $settings[$setting] = $row ? self::castValue($desc['type'], $row->value) : null;
            }

            return $settings;
        } else {
            $desc = self::DESCRIPTIONS[$name] ?? null;
            if (!$desc) {
                throw new \InvalidArgumentException("'$name' is an unknown setting.");
            }

            if (isset(self::$cache[$name])) {
                return self::$cache[$name];
            }

            $row = self::select('value')->where('name', $name)->first();

            $value = $row ? self::castValue($desc['type'], $row->value) : null;
            self::$cache[$name] = $value;
            return $value;
        }
    }

    public static function castValue($type, $value)
    {
        // Convert the values
        switch ($type) {
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            default:
                return $value;
        }
    }

    public function getTypeAttribute()
    {
        $desc = self::DESCRIPTIONS[$this->name] ?? null;
        return  $desc ? $desc['type'] : null;
    }

    public function getDescriptionAttribute()
    {
        $desc = self::DESCRIPTIONS[$this->name] ?? null;
        return $desc ? $desc['description'] : null;
    }

    public function getOptionsAttribute()
    {
        $desc = self::DESCRIPTIONS[$this->name] ?? null;
        return $desc ? ($desc['options'] ?? null) : null;
    }

    public function getIsCredentialAttribute()
    {
        $desc = self::DESCRIPTIONS[$this->name] ?? null;
        return $desc ? ($desc['is_credential'] ?? false) : null;
    }
}
