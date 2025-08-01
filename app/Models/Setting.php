<?php

namespace App\Models;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\ClubhouseCache;
use Exception;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class Setting extends ApiModel
{
    protected $table = 'setting';
    public $timestamps = true;

    // Allow all fields to be filled.
    protected $guarded = [];

    protected $primaryKey = 'name';
    public $incrementing = false;

    protected bool $auditModel = true;

    protected $rules = [
        'name' => 'required|string',
        'value' => 'string|nullable'
    ];

    public $appends = [
        'type',
        'description',
        'is_credential',
        'options'
    ];


    const string TYPE_BOOL = 'bool';
    const string TYPE_DATE = 'date';
    const string TYPE_DATETIME = 'datetime';
    const string TYPE_EMAIL = 'email';
    const string TYPE_FLOAT = 'float';
    const string TYPE_INTEGER = 'integer';
    const string TYPE_STRING = 'string';
    const string TYPE_URL = 'url';

    /*
     * Each setting must be described in the table below.
     *
     * The definitions are:
     * description: single line detail on what the setting is
     * type: the value type (bool,string,json,email,date,datetime,integer,float)
     * is_credential (optional) - set true if the setting is a credential and should not be included in a redacted
     *         database dump, the value returned in an API lookup response, or prevented from being set by a non-Tech Ninja
     * options: array of possible options format is [ 'option', 'description' ]
     */

    const array DESCRIPTIONS = [
        'AccountCreationEmail' => [
            'description' => 'Alert email address when accounts register',
            'type' => self::TYPE_EMAIL,
        ],

        'AdminEmail' => [
            'description' => 'Ranger Tech Team Email Address',
            'type' => self::TYPE_EMAIL,
        ],

        'AllowSignupsWithoutPhoto' => [
            'description' => 'Allow shift signups without requiring an approved photo',
            'type' => self::TYPE_BOOL,
        ],


        'AllYouCanEatEventWeekThreshold' => [
            'description' => 'Event week hour threshold to earn an All-You-Can-Eat-Pass',
            'type' => self::TYPE_INTEGER
        ],

        'AllYouCanEatEventPeriodThreshold' => [
            'description' => 'Event period (pre-event,event,post-event weeks) hour threshold to earn an All-You-Can-Eat-Pass',
            'type' => self::TYPE_INTEGER
        ],

        'AuditorRegistrationDisabled' => [
            'description' => 'Prevent Auditors from registering for an account in the Clubhouse',
            'type' => self::TYPE_BOOL,
            'default' => false,
        ],

        'BroadcastClubhouseNotify' => [
            'description' => 'Enable RBS notification of new Clubhouse messages',
            'type' => self::TYPE_BOOL,
        ],

        'BroadcastClubhouseSandbox' => [
            'description' => 'Enable RBS Clubhouse Message sandbox mode (Clubhouse messages not created)',
            'type' => self::TYPE_BOOL,
        ],

        'BroadcastMailSandbox' => [
            'description' => 'Enable RBS sandbox email mode',
            'type' => self::TYPE_BOOL,
        ],

        'BroadcastSMSSandbox' => [
            'description' => 'Sandbox SMS messages',
            'type' => self::TYPE_BOOL,
        ],

        'ChatGPTToken' => [
            'description' => 'ChatGPT token used to process freeform text (handle extraction, etc)',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'DailyReportEmail' => [
            'description' => 'Email address to send the Clubhouse Daily Report',
            'type' => self::TYPE_EMAIL,
        ],

        'DashboardPeriod' => [
            'description' => 'The Dashboard Period',
            'type' => self::TYPE_STRING,
            'default' => 'auto',
            'options' => [
                ['after-event', 'After Event / Off Season (November thru March)'],
                ['before-event', 'Before Event (November thru mid-to-late August)'],
                ['event', 'Event Period (Early Man through October)'],
                ['post-event', 'Post Event (September thru November)'],
            ]
        ],

        'DatabaseCreatedOn' => [
            'description' => 'For the Staging & Training servers, the date database was created on. (automatically set)',
            'type' => self::TYPE_DATETIME,
            'default' => '',
        ],

        'DoNotReplyEmail' => [
            'description' => 'Most generated Clubhouse emails have a reply to of do-not-reply@XXX.org',
            'type' => self::TYPE_EMAIL,
            'default' => 'do-not-reply@burningmail.burningman.org'
        ],

        'EditorUrl' => [
            'description' => 'The script URL of the WYSIWYG editor (currently TinyMCE)',
            'type' => self::TYPE_URL
        ],

        'GoogleAnalyticsID' => [
            'description' => 'The Google Analytics property ID',
            'type' => self::TYPE_STRING,
        ],

        'HQWindowInterfaceEnabled' => [
            'description' => 'Enable the HQ Window Interface (normally enabled during the event)',
            'type' => self::TYPE_BOOL,
            'default' => true,
        ],

        'JoiningRangerSpecialTeamsUrl' => [
            'description' => 'How To Join Ranger Special Teams Document URL',
            'type' => self::TYPE_STRING,
        ],

        'MailingListUpdateRequestEmail' => [
            'description' => 'Email address(es) to send a message when an active Ranger requests to update the mailing list subscriptions',
            'type' => self::TYPE_EMAIL,
        ],

        'MealDates' => [
            'description' => 'Commissary dates and hours',
            'type' => self::TYPE_STRING,
        ],

        'MealHalfPogEnabled' => [
            'description' => 'Enable or disable issuing of half meal pogs',
            'type' => self::TYPE_BOOL,
            'default' => true,
        ],

        'MealInfoAvailable' => [
            'description' => 'True if meal information is available.',
            'type' => self::TYPE_BOOL,
        ],

        'MentorEmail' => [
            'description' => 'Mentor Cadre email. Shown to Alphas when suggesting contacting the cadre.',
            'type' => self::TYPE_EMAIL
        ],

        'MotorPoolProtocolEnabled' => [
            'description' => 'Allow the Motor Pool Protocol to be signed, and to enable the dashboard prompt',
            'type' => self::TYPE_BOOL,
            'default' => false,
        ],

        'MVRDeadline' => [
            'description' => 'The Clubhouse will show the MVR request form link, if eligible, up until the month and day (not year) at 23:59. Format is MM-DD',
            'type' => self::TYPE_STRING,
        ],

        'OnboardAlphaShiftPrepLink' => [
            'description' => 'Used by the Onboarding Checklist for PNVs. Link to how to prep for your Alpha Shift in the Ranger Manual',
            'type' => self::TYPE_STRING
        ],

        'OnlineCourseEnabled' => [
            'description' => 'Enable online course link',
            'type' => self::TYPE_BOOL
        ],

        'OnlineCourseSiteUrl' => [
            'description' => 'Online course Url',
            'type' => self::TYPE_STRING
        ],

        'OnlineCourseDisabledAllowSignups' => [
            'description' => 'Enable shift signups even if the Online Course is disabled (VERY DANGEROUS)',
            'type' => self::TYPE_BOOL,
            'default' => false,
        ],

        'OnlineCourseOnlyForAuditors' => [
            'description' => 'Auditor are only allowed to take the Online Course',
            'type' => self::TYPE_BOOL,
            'default' => false
        ],

        'OnlineCourseOnlyForBinaries' => [
            'description' => 'Only require Online Course and not In-Person training for vets (2+ years)',
            'type' => self::TYPE_BOOL,
            'default' => false
        ],

        'OnlineCourseOnlyForVets' => [
            'description' => 'Only require the Online Course and not In-Person training for binaries (0-1 years)',
            'type' => self::TYPE_BOOL,
            'default' => false
        ],

        'MoodleDomain' => [
            'description' => 'The LMS domain name',
            'type' => self::TYPE_STRING
        ],

        'MoodleToken' => [
            'description' => 'Moodle Web Service Token',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'MoodleHalfCourseId' => [
            'description' => 'Moodle online course ID for active Rangers (2+ years)',
            'type' => self::TYPE_STRING,
        ],

        'MoodleFullCourseId' => [
            'description' => 'Moodle full online course ID for PNVs, Auditors, Binaries, and Inactive Rangers',
            'type' => self::TYPE_STRING,
        ],

        'MoodleServiceName' => [
            'description' => 'Moodle service name to use',
            'type' => self::TYPE_STRING,
        ],

        'MoodleStudentRoleID' => [
            'description' => 'The LMS (Moodle) role id to assign to new users (usually student)',
            'type' => self::TYPE_INTEGER,
        ],

        'PersonnelEmail' => [
            'description' => 'Ranger Personnel Email Address',
            'type' => self::TYPE_EMAIL,
        ],

        'PhotoAnalysisEnabled' => [
            'description' => 'Run all uploaded photos through AWS Rekognition face detection',
            'type' => self::TYPE_BOOL,
        ],

        'PhotosExpiredEmail' => [
            'description' => 'Weekly email send when archived photos older than 6 months have been deleted',
            'type' => self::TYPE_EMAIL,
        ],

        'PhotoRekognitionAccessKey' => [
            'description' => 'AWS Rekognition Access Key used for BMID photo analysis',
            'type' => self::TYPE_STRING,
            'is_credential' => true
        ],

        'PhotoRekognitionAccessSecret' => [
            'description' => 'AWS Rekognition Secret Key used for BMID photo analysis',
            'type' => self::TYPE_STRING,
            'is_credential' => true
        ],

        'PhotoPendingNotifyEmail' => [
            'description' => 'Email(s) to notify when photos are queued up for review. (nightly mail)',
            'type' => self::TYPE_EMAIL
        ],

        'PhotoUploadEnable' => [
            'description' => 'Enable Photo Uploading. If disabled, Admins and VCs will still be able to upload photos on another person\'s behalf.',
            'type' => self::TYPE_BOOL,
        ],

        'ProspectiveApplicationMailSandbox' => [
            'description' => 'Prospective Application Mail Sandbox. Leave blank to allow applicant emails to go to the applicant. For testing purposes, enter an email address to where the messages should be sent to instead.',
            'type' => self::TYPE_EMAIL,
        ],

        'RadioCheckoutAgreementEnabled' => [
            'description' => 'Allows the Radio Checkout Agreement to be signed.',
            'type' => self::TYPE_BOOL,
        ],

        'RadioInfoAvailable' => [
            'description' => 'True if radio information has been uploaded.',
            'type' => self::TYPE_BOOL,
        ],

        'RangerFeedbackFormUrl' => [
            'description' => 'Ranger Feedback Form URL',
            'type' => self::TYPE_STRING,
        ],

        'RangerManualUrl' => [
            'description' => 'The current Ranger Manual document',
            'type' => self::TYPE_STRING
        ],

        'RangerPoliciesUrl' => [
            'description' => 'Ranger Policy Document URL',
            'type' => self::TYPE_STRING,
        ],

        'RangerPersonalVehiclePolicyUrl' => [
            'description' => 'Ranger Personal Vehicle Document URL (used by Me > Vehicles)',
            'type' => self::TYPE_STRING,
        ],

        'RRNEmail' => [
            'description' => 'The Ranger Regional Network team email address. Use to process Ranger applications.',
            'type' => self::TYPE_EMAIL,
        ],

        'SpTicketThreshold' => [
            'description' => 'Credit threshold for a Special Price Ticket. Shown on the Schedule and Ticket announce pages',
            'type' => self::TYPE_FLOAT,
        ],

        'TicketMailPriorityPrice' => [
            'description' => 'The price to ship SPTs and Gift Tickets via USPS priority shipping',
            'type' => self::TYPE_FLOAT,
        ],

        'TicketMailStandardPrice' => [
          'description' => 'The price to ship SPTs and Gift Tickets via standard USPS standard shipping',
            'type' => self::TYPE_FLOAT,
        ],

        'SandmanRequireAffidavit' => [
            'description' => 'Require the Sandman Affidavit be signed in order to work a Sandman shift',
            'type' => self::TYPE_BOOL,
            'default' => 'false',
        ],

        'SandmanRequirePerimeterExpWithinYears' => [
            'description' => 'Require a Sandman to have worked a Burn Perimeter or Sandman positions within X years. Leave blank to skip this check.',
            'type' => self::TYPE_INTEGER,
            'default' => 5,
        ],

        'SFEnableWritebacks' => [
            'description' => 'Enable Salesforce Object Update',
            'type' => self::TYPE_BOOL,
        ],

        'SFprdAuthUrl' => [
            'description' => 'Salesforce Production Authentication URL',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'SFprdClientId' => [
            'description' => 'Salesforce Production Client ID',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'SFprdClientSecret' => [
            'description' => 'Salesforce Production Client Secret',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'SFprdPassword' => [
            'description' => 'Salesforce Production Password (login password + security token)',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'SFprdUsername' => [
            'description' => 'Salesforce Production Username',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'ScTicketThreshold' => [
            'description' => 'Credit threshold for staff credential. Shown on the Schedule and Ticket announce pages',
            'type' => self::TYPE_FLOAT,
        ],

        'SendWelcomeEmail' => [
            'description' => 'Enable Welcome email when an account is created',
            'type' => self::TYPE_BOOL,
        ],

        'ShiftManagementSelfEnabled' => [
            'description' => 'Enable Shift Management Self permission',
            'type' => self::TYPE_BOOL,
            'default' => false,
        ],

        'ShiftSignupFromEmail' => [
            'description' => 'From email address for shift sign up messages',
            'type' => self::TYPE_EMAIL,
        ],

        'ShiftCheckInEarlyPeriod' => [
            'description' => 'How soon, in minutes, is the person allowed to check into a shift. Set to blank to disable the check.',
            'type' => self::TYPE_INTEGER,
            'default' => 30
        ],

        'ShiftCheckInLatePeriod' => [
            'description' => 'How late, in minutes, is the person allowed to check into a shift. Set to blank to disable the check.',
            'type' => self::TYPE_INTEGER,
            'default' => 15,
        ],

        'ShirtLongSleeveHoursThreshold' => [
            'description' => 'Hour threshold to earn a long sleeve shirt',
            'type' => self::TYPE_INTEGER,
        ],

        'ShirtShortSleeveHoursThreshold' => [
            'description' => 'Hour threshold to earn a short sleeve shirt/t-shirt',
            'type' => self::TYPE_INTEGER,
        ],

        'ShowerPogThreshold' => [
            'description' => 'Hour threshold to earn a shower pog to The Wet Spot',
            'type' => self::TYPE_INTEGER
        ],

        'ShowerAccessEventWeekThreshold' => [
            'description' => 'Event week hour threshold to earn shower access for the next event',
            'type' => self::TYPE_INTEGER,
            'default' => 60,
        ],

        'ShowerAccessEntireEventThreshold' => [
            'description' => 'Pre-event/event/post-event period hour threshold to earn shower access for the next event',
            'type' => self::TYPE_INTEGER,
            'default' => 130,

        ],

        'TAS_Alpha_FAQ' => [
            'description' => 'Alpha WAP FAQ Link',
            'type' => self::TYPE_STRING,
        ],

        'TAS_BoxOfficeOpenDate' => [
            'description' => 'Playa Box Office Opening date and time',
            'type' => self::TYPE_DATETIME,
        ],

        'TAS_DefaultAlphaWAPDate' => [
            'description' => 'Default Alpha WAP Access Date',
            'type' => self::TYPE_DATE,
        ],

        'TAS_DefaultSOWAPDate' => [
            'description' => 'Default WAP SO Access Date',
            'type' => self::TYPE_DATE,
        ],

        'TAS_DefaultWAPDate' => [
            'description' => 'Default WAP Access Date',
            'type' => self::TYPE_DATE,
        ],

        'TAS_Email' => [
            'description' => 'Ranger Ticketing Support Email',
            'type' => self::TYPE_EMAIL,
        ],

        'TAS_LSD_PayByDateTime' => [
            'description' => 'The date and time LSD items have to be paid for. Shown on various banners.',
            'type' => self::TYPE_DATETIME,
        ],

        'TAS_PayByDateTime' => [
            'description' => 'The date and time items have (non-LSD & Gift) to be paid for. Shown on the Ticketing is closed page.',
            'type' => self::TYPE_DATETIME,
        ],

        'TAS_Pickup_Locations' => [
            'description' => 'Locations w/hours to pickup staff credentials and will-call items. Shown on the ticketing page',
            'type' => self::TYPE_STRING,
        ],

        'TAS_Special_Price_Vehicle_Pass_Cost' => [
            'description' => 'Special Price Vehicle Pass in dollars. (cents not supported)',
            'type' => self::TYPE_INTEGER,
        ],

        'TAS_Special_Price_Ticket_Cost' => [
            'description' => 'Special Price Ticket cost in dollars. (cents not supported)',
            'type' => self::TYPE_INTEGER,
        ],

        'TAS_SubmitDate' => [
            'description' => 'Use to tell the user when the regular ticketing window will closed. Format: YYYY-MM-DD.',
            'type' => self::TYPE_STRING,
        ],

        'TAS_Ticket_FAQ' => [
            'description' => 'Ticketing FAQ Link',
            'type' => self::TYPE_STRING,
        ],

        'TAS_Provisions_FAQ' => [
            'description' => 'Ticketing Provisions FAQ Link',
            'type' => self::TYPE_STRING,
        ],

        'TAS_VP_FAQ' => [
            'description' => 'Vehicle Pass FAQ Link',
            'type' => self::TYPE_STRING,
        ],

        'TAS_SAPDateRange' => [
            'description' => 'Allowable SC & WAP August day range. Use used Format: DD-DD. (just the start & ending days, not month). Use to build WAP date options and the TRS spreadsheet.',
            'type' => self::TYPE_STRING,
        ],

        'TAS_SAPSOMax' => [
            'description' => 'Max. WAP SO Count',
            'type' => self::TYPE_INTEGER,
        ],

        'TAS_SAP_FAQ' => [
            'description' => 'WAP FAQ Link',
            'type' => self::TYPE_STRING,
        ],

        'TAS_SAP_Report_Email' => [
            'description' => 'Email address(es) to send nightly SAPs Needed Report to',
            'type' => self::TYPE_EMAIL
        ],

        'ThankYouCardsHash' => [
            'description' => 'Thank You card page password. SHA-256 encoded.',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'TicketVendorEmail' => [
            'description' => 'Ticketing Vendor Support Email',
            'type' => self::TYPE_EMAIL,
        ],

        'TicketVendorName' => [
            'description' => 'Ticketing Vendor Name',
            'type' => self::TYPE_STRING,
        ],

        'TicketingPeriod' => [
            'description' => 'Ticketing Period / Season',
            'type' => self::TYPE_STRING,
            'options' => [
                ['offseason', 'off season - show banked tickets'],
                ['announce', 'announce - tickets have been awarded but ticketing window is not open'],
                ['open', 'open - tickets can be claimed'],
                ['closed', 'closed - show claims and banks. Changes not directly allowed'],
            ]
        ],


        'TicketsAndStuffEnablePNV' => [
            'description' => 'Enable Ticketing Page for PNVs',
            'type' => self::TYPE_BOOL,
        ],

        'TimesheetCorrectionEnable' => [
            'description' => 'Allow users to submit Timesheet Corrections. Note: The Timecard Year Round permission allows access year round.',
            'type' => self::TYPE_BOOL,
        ],

        'TrainingSeasonalRoleEnabled' => [
            'description' => "Enable Training Seasonal Role",
            'type' => self::TYPE_BOOL,
            'default' => 'false',
        ],

        'TrainingAcademyEmail' => [
            'description' => 'Training Academy Email',
            'type' => self::TYPE_EMAIL,
        ],

        'TrainingSignupFromEmail' => [
            'description' => 'From email address for training sign up messages',
            'type' => self::TYPE_EMAIL,
        ],

        'TroubleshooterMentorSurveyURL' => [
            'description' => 'If a person worked a TS Mentor shift, present this url on the dashboard',
            'type' => self::TYPE_URL,
        ],

        'TwilioAccountSID' => [
            'description' => 'Twilio Account SID',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'TwilioAuthToken' => [
            'description' => 'Twilio Authentication Token',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'TwilioServiceId' => [
            'description' => 'Twilio Service ID of SMS Channel',
            'type' => self::TYPE_STRING,
            'is_credential' => true,
        ],

        'VCEmail' => [
            'description' => 'Ranger Volunteer Coordinator Address',
            'type' => self::TYPE_EMAIL,
        ],

        'VehiclePendingEmail' => [
            'description' => 'Email(s) to notify when vehicle requests are queued up for review. (nightly mail)',
            'type' => self::TYPE_EMAIL
        ],

        'EventManagementOnPlayaEnabled' => [
            'description' => 'Enables Event Management (E.M.) On Playa role AND allows E.M. Year Round to view Emergency Contact Info plus read Clubhouse Messages',
            'type' => self::TYPE_BOOL
        ]
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (isset($this->name) && !isset($this->value) && isset(self::DESCRIPTIONS[$this->name]['default'])) {
            $this->value = self::DESCRIPTIONS[$this->name]['default'];
        }
    }

    /**
     * Find a setting. Must be defined in the DESCRIPTIONS table
     */

    public static function find($id)
    {
        $desc = self::DESCRIPTIONS[$id] ?? null;

        if (!$desc) {
            // Setting is not defined.
            return null;
        }

        // Lookup the value
        return Setting::firstOrNew(['name' => $id]);
    }

    /**
     * Find a setting or throw an exception
     */

    public static function findOrFail($id)
    {
        $row = self::find($id);

        if ($row) {
            return $row;
        }

        throw (new ModelNotFoundException)->setModel(Setting::class, $id);
    }

    /**
     * Retrieve all the setting values.
     *
     * @return array<string,Setting>
     */

    public static function findAll(): array
    {
        $rows = Setting::all()->keyBy('name');

        $settings = [];
        foreach (self::DESCRIPTIONS as $name => $desc) {
            $settings[] = $rows[$name] ?? new Setting(['name' => $name]);
        }

        usort($settings, fn($a, $b) => strcasecmp($a->name, $b->name));

        return $settings;
    }

    /**
     * Restart the queues when a setting is updated.
     *
     * @return void
     */

    public static function kickQueues()
    {
        if (app()->isProduction()) {
            try {
                // Kick the queue workers to pick up the new settings
                Artisan::call('queue:restart');
            } catch (Exception $e) {
                ErrorLog::recordException($e, 'setting-queue-restart-exception');
            }
        }
    }

    /**
     * Get the value of a setting(s)
     *
     * @param string|array $setting
     * @param bool $throwOnEmpty
     * @return mixed
     */

    public static function getValue(string|array $setting, bool $throwOnEmpty = false): mixed
    {
        $isArray = is_array($setting);

        $names = $isArray ? $setting : [$setting];

        $lookup = [];
        $values = [];

        // Find all the cached settings
        foreach ($names as $name) {
            $desc = self::DESCRIPTIONS[$name] ?? null;
            if (!$desc) {
                throw new UnacceptableConditionException("'$name' is an unknown setting.");
            }

            if (self::getCached($name, $value, $desc['type'])) {
                if ($isArray) {
                    $values[$name] = $value;
                } else {
                    return $value;
                }
            } else {
                $lookup[] = $name;
            }
        }

        if (empty($lookup)) {
            // Everything was cached
            return $values;
        }

        // Lookup the settings
        $rows = self::select('name', 'value')
            ->whereIn('name', $lookup)
            ->get()
            ->keyBy('name');

        foreach ($lookup as $name) {
            $row = $rows[$name] ?? null;
            $desc = self::DESCRIPTIONS[$name];

            $value = self::storedOrDefault($row, $desc, $throwOnEmpty);
            self::setCached($name, $value);
            if ($isArray) {
                $values[$name] = $value;
            } else {
                return $value;
            }
        }

        return $values;
    }

    public static function getCached($name, &$value, $type): bool
    {
        $cache = self::cacheStore();
        if ($cache->has($name)) {
            $value = self::castValue($type, $cache->get($name));
            return true;
        }

        return false;
    }

    public static function setCached($name, $value)
    {
        self::cacheStore()->put($name, $value);
    }

    public static function cacheStore(): Repository
    {
        return app()->isLocal() ? Cache::store('array') : ClubhouseCache::store();
    }

    public static function storedOrDefault($row, $desc, bool $throwOnEmpty)
    {
        if ($row) {
            $value = self::castValue($desc['type'], $row->value);
        } else if (isset($desc['default'])) {
            $value = self::castValue($desc['type'], $desc['default']);
        } else {
            $value = null;
        }

        if ($throwOnEmpty && self::isEmpty($value)) {
            throw new RuntimeException("Setting is empty.");
        }

        return $value;
    }

    public static function castValue($type, $value)
    {
        return match ($type) {
            self::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INTEGER => (int)$value,
            self::TYPE_FLOAT => (float)$value,
            default => $value,
        };
    }

    public static function isEmpty($value): bool
    {
        return (!is_numeric($value) && !is_bool($value) && empty($value));
    }

    public function getTypeAttribute()
    {
        return self::DESCRIPTIONS[$this->name]['type'] ?? null;
    }

    public function getDescriptionAttribute()
    {
        return self::DESCRIPTIONS[$this->name]['description'] ?? null;
    }

    public function getOptionsAttribute()
    {
        return self::DESCRIPTIONS[$this->name]['options'] ?? null;
    }

    public function getIsCredentialAttribute()
    {
        return self::DESCRIPTIONS[$this->name]['is_credential'] ?? false;
    }
}
