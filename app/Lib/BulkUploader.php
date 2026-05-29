<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkUpload\Handlers\BmidHandler;
use App\Lib\BulkUpload\Handlers\CertificationHandler;
use App\Lib\BulkUpload\Handlers\EventColumnHandler;
use App\Lib\BulkUpload\Handlers\Handler;
use App\Lib\BulkUpload\Handlers\PersonColumnHandler;
use App\Lib\BulkUpload\Handlers\PersonStatusHandler;
use App\Lib\BulkUpload\Handlers\ProvisionHandler;
use App\Lib\BulkUpload\Handlers\SapHandler;
use App\Lib\BulkUpload\Handlers\TeamMembershipHandler;
use App\Lib\BulkUpload\Handlers\TicketHandler;
use App\Lib\BulkUpload\Record;
use App\Models\ActionLog;
use App\Models\Person;
use App\Models\Provision;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class BulkUploader
{
    const string STATUS_SUCCESS = 'success';
    const string STATUS_FAILED = 'failed';
    const string STATUS_WARNING = 'warning';
    const string STATUS_CALLSIGN_NOT_FOUND = 'callsign-not-found';

    const string CHANGE_PERSON_COLUMN_ACTION = 'changePersonColumn';
    const string CHANGE_EVENT_COLUMN_ACTION = 'changeEventColumn';
    const string CHANGE_PERSON_STATUS_ACTION = 'changePersonStatus';
    const string PROCESS_BMID_ACTION = 'processBmid';
    const string PROCESS_PROVISIONS_ACTION = 'processProvisions';
    const string PROCESS_TICKETS_ACTION = 'processTickets';
    const string PROCESS_WAP_ACTION = 'processSAPs';
    const string CERTIFICATION_ACTION = 'processCertifications';
    const string PROCESS_TEAM_MEMBERSHIP = 'processTeamMembership';

    const string HELP_CALLSIGN = 'callsign';
    const string HELP_RADIO = 'callsign,radio count';
    const string HELP_CERTIFICATION = "callsign,issued on date,card number,trained on date\ndate format = YYYY-MM-DD\nAll fields, other than the callsign, are optional and may be left blank.
    Examples: hubcap,,12345 to record the card number\nhubcap,2022-01-4,,2021-12-20 to record the issued on and trained on dates.";

    const string HELP_PROVISIONS = "callsign[,source year,expiry year] = Source year and expiry year are optional.";
    const string HELP_PROVISION_EVENT_RADIO = "callsign,count[,source year,expiry year] = Source year and expiry year are optional";

    // Meal pass combinations. These ids match BulkUpload\MealPass cases and are
    // the action ids that arrive from the UI.
    const string ALL_EAT_PASS = 'all_eat_pass';
    const string EVENT_EAT_PASS = 'event_eat_pass';
    const string PRE_EVENT_EAT_PASS = 'pre_event_eat_pass';
    const string POST_EVENT_EAT_PASS = 'post_event_eat_pass';
    const string PRE_EVENT_EVENT_EAT_PASS = 'pre_event_event_eat_pass';
    const string PRE_POST_EAT_PASS = 'pre_post_eat_pass';
    const string EVENT_POST_EAT_PASS = 'event_post_event_eat_pass';

    // Note: certification actions are appended by BulkUploadController::actions().

    const array ACTION_DESCRIPTIONS = [
        [
            'label' => 'Earned Provisions Actions',
            'options' => [
                ['id' => self::ALL_EAT_PASS, 'label' => 'Earned All Eat Pass', 'help' => self::HELP_PROVISIONS],
                ['id' => self::EVENT_EAT_PASS, 'label' => 'Earned Event Eat Pass', 'help' => self::HELP_PROVISIONS],
                ['id' => Provision::WET_SPOT, 'label' => 'Earned Wet Spot', 'help' => self::HELP_PROVISIONS],
                [
                    'id' => Provision::EVENT_RADIO,
                    'label' => 'Earned Event Radio',
                    'help' => self::HELP_PROVISION_EVENT_RADIO,
                ]
            ]
        ],
        [
            'label' => 'Allocated Provisions Actions',
            'options' => [
                ['id' => 'alloc_' . self::ALL_EAT_PASS, 'label' => 'Allocated All Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . self::EVENT_EAT_PASS, 'label' => 'Allocated Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . self::PRE_EVENT_EAT_PASS, 'label' => 'Allocated Pre-Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . self::PRE_EVENT_EVENT_EAT_PASS, 'label' => 'Allocated Pre-Event + Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . self::PRE_POST_EAT_PASS, 'label' => 'Allocated Pre+Post Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . self::EVENT_POST_EAT_PASS, 'label' => 'Allocated Event + Post Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . self::POST_EVENT_EAT_PASS, 'label' => 'Allocated Post-Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . Provision::WET_SPOT, 'label' => 'Allocated Wet Spot', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . Provision::EVENT_RADIO, 'label' => 'Allocated Event Radio', 'help' => self::HELP_RADIO]
            ]
        ],
        [
            'label' => 'BMID Actions',
            'options' => [
                [
                    'id' => 'bmidsubmitted',
                    'label' => 'Mark BMID as submitted',
                    'help' => self::HELP_CALLSIGN
                ],
            ]
        ],
        [
            'label' => 'Ticket Actions',
            'options' => [
                [
                    'id' => 'tickets',
                    'label' => 'Create Access Documents',
                    'help' => "callsign,type[,access date]\ntype = cred (Staff Credential), spt (Special Price Ticket), gift (Gift Ticket), vpgift (Gift Vehicle Pass), lsd (Late Season Directed), vplsd (LSD Vehicle Pass), vp (Special Price Vehicle Pass), sap (Setup Access Pass)\n\nAdvanced usage: callsign,type[,access date,source year, expiry year]\nsource year and expiry year are only supported for cred and spt",
                ],
                [
                    'id' => 'wap',
                    'label' => 'Update SAP dates',
                    'help' => "callsign,date\ndate = YYYY-MM-DD or any (for anytime access)"
                ],
            ]
        ],
        [
            'label' => 'Vehicle Paperwork Flags',
            'options' => [
                ['id' => 'signed_motorpool_agreement', 'label' => 'Signed Motorpool Agreement (gators/golf carts)', 'help' => self::HELP_CALLSIGN],
                ['id' => 'org_vehicle_insurance', 'label' => 'Has Org Vehicle Insurance (MVR)', 'help' => self::HELP_CALLSIGN],
                ['id' => 'mvr_eligible', 'label' => 'May Submit a MVR Request', 'help' => self::HELP_CALLSIGN],
                ['id' => 'may_request_stickers', 'label' => 'May Request Vehicle Use Items', 'help' => self::HELP_CALLSIGN],
            ]
        ],
        [
            'label' => 'Affidavits',
            'options' => [
                ['id' => 'sandman_affidavit', 'label' => 'Signed Sandman Affidavit', 'help' => self::HELP_CALLSIGN]
            ]
        ],
        [
            'label' => 'Change Status',
            'options' => [
                ['id' => 'active', 'label' => 'set as active', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alpha', 'label' => 'set as alpha', 'help' => self::HELP_CALLSIGN],
                ['id' => 'inactive', 'label' => 'set as inactive', 'help' => self::HELP_CALLSIGN],
                ['id' => 'prospective', 'label' => 'set as prospective', 'help' => self::HELP_CALLSIGN],
                ['id' => 'retired', 'label' => 'set as retired', 'help' => self::HELP_CALLSIGN],
                ['id' => 'vintage', 'label' => 'set vintage flag', 'help' => self::HELP_CALLSIGN]
            ]
        ],
        [
            'label' => 'Team Membership',
            'options' => [
                [
                    'id' => 'team_membership',
                    'label' => 'Add Team Membership History',
                    'help' => "callsign,team name,joined on,left on\njoined on = YYYY-MM-DD date the person joined the team.\nleft on= YYYY-MM-DD optional date the person left the time.\n\nThis action only records team membership history and does not effect current membership as managed thru \"Edit Teams/Positions\""
                ]
            ]
        ],
    ];

    const array ACTIONS = [
        'vintage' => self::CHANGE_PERSON_COLUMN_ACTION,

        'org_vehicle_insurance' => self::CHANGE_EVENT_COLUMN_ACTION,
        'signed_motorpool_agreement' => self::CHANGE_EVENT_COLUMN_ACTION,
        'may_request_stickers' => self::CHANGE_EVENT_COLUMN_ACTION,
        'sandman_affidavit' => self::CHANGE_EVENT_COLUMN_ACTION,
        'mvr_eligible' => self::CHANGE_EVENT_COLUMN_ACTION,

        'active' => self::CHANGE_PERSON_STATUS_ACTION,
        'alpha' => self::CHANGE_PERSON_STATUS_ACTION,
        'inactive' => self::CHANGE_PERSON_STATUS_ACTION,
        'past prospective' => self::CHANGE_PERSON_STATUS_ACTION,
        'prospective waitlist' => self::CHANGE_PERSON_STATUS_ACTION,
        'prospective' => self::CHANGE_PERSON_STATUS_ACTION,
        'retired' => self::CHANGE_PERSON_STATUS_ACTION,

        'meals' => self::PROCESS_BMID_ACTION,
        'showers' => self::PROCESS_BMID_ACTION,
        'bmidsubmitted' => self::PROCESS_BMID_ACTION,

        'all_eat_pass' => self::PROCESS_PROVISIONS_ACTION,
        'event_eat_pass' => self::PROCESS_PROVISIONS_ACTION,
        'event_radio' => self::PROCESS_PROVISIONS_ACTION,
        'wet_spot' => self::PROCESS_PROVISIONS_ACTION,

        'tickets' => self::PROCESS_TICKETS_ACTION,

        'wap' => self::PROCESS_WAP_ACTION,
        'sap' => self::PROCESS_WAP_ACTION,

        'team_membership' => self::PROCESS_TEAM_MEMBERSHIP,
    ];

    private const array HANDLERS = [
        self::CHANGE_PERSON_COLUMN_ACTION => PersonColumnHandler::class,
        self::CHANGE_EVENT_COLUMN_ACTION => EventColumnHandler::class,
        self::CHANGE_PERSON_STATUS_ACTION => PersonStatusHandler::class,
        self::PROCESS_BMID_ACTION => BmidHandler::class,
        self::PROCESS_PROVISIONS_ACTION => ProvisionHandler::class,
        self::PROCESS_TICKETS_ACTION => TicketHandler::class,
        self::PROCESS_WAP_ACTION => SapHandler::class,
        self::CERTIFICATION_ACTION => CertificationHandler::class,
        self::PROCESS_TEAM_MEMBERSHIP => TeamMembershipHandler::class,
    ];

    /**
     * Process a callsign list according to the given action.
     *
     * @throws UnacceptableConditionException
     * @return list<array<string, mixed>>
     */
    public static function process(string $action, bool $commit, string $reason, string $recordsParam): array
    {
        $records = self::parseRecords($recordsParam);
        if ($records === []) {
            return [];
        }

        self::hydratePersons($records);
        self::handlerFor($action)->process($records, $action, $commit, $reason);

        $results = array_map(fn (Record $record) => self::buildResult($record), $records);

        if ($commit) {
            ActionLog::record(Auth::user(), 'bulk-upload', 'bulk upload commit', [
                'action' => $action,
                'reason' => $reason,
                'records' => $recordsParam,
                'results' => $results,
            ]);
        }

        return $results;
    }

    /**
     * Save a model, marking the record as failed on a query exception.
     */
    public static function saveModel($model, Record $record): bool
    {
        try {
            $model->saveWithoutValidation();
            return true;
        } catch (QueryException $e) {
            $record->fail('SQL Failure ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return the default source/expiry years for a freshly-issued ticket
     * or provision. The $isProvision flag is currently informational; both
     * cases use the same window, but the controller calls this twice so
     * the frontend can vary independently in the future.
     *
     * @return array{int, int}
     */
    public static function defaultYears(bool $isProvision): array
    {
        $now = now();
        if ($now->month >= 9) {
            return [$now->year, $now->year + 4];
        }
        return [$now->year - 1, $now->year + 3];
    }

    /**
     * Validate that $input is a numeric year within +/- 5 years of the
     * current year and (for source years) not in the future. On success,
     * writes the parsed value into $year by reference and returns true.
     */
    public static function checkYearRange(int &$year, string $input, Record $record, bool $isSource = false): bool
    {
        $label = $isSource ? 'Source Year' : 'Expiry Year';

        $input = trim($input);
        if (!is_numeric($input)) {
            $record->fail("$label is not a number");
            return false;
        }

        $parsed = (int)$input;
        $currentYear = current_year();
        if ($parsed < $currentYear - 5) {
            $record->fail("$label is more than 5 years in the past");
            return false;
        }
        if ($isSource && $parsed > $currentYear) {
            $record->fail("$label is in the future [$parsed]");
            return false;
        }
        if ($parsed > $currentYear + 5) {
            $record->fail("$label is more than 5 years in the future");
            return false;
        }

        $year = $parsed;
        return true;
    }

    /**
     * True when $date is empty or parses as a valid Carbon date.
     */
    public static function dateIsValid(?string $date): bool
    {
        if (empty($date)) {
            return true;
        }
        try {
            Carbon::parse($date);
            return true;
        } catch (InvalidFormatException) {
            return false;
        }
    }

    /**
     * @return list<Record>
     */
    private static function parseRecords(string $recordsParam): array
    {
        $records = [];
        $lines = explode("\n", str_replace("\r", '', $recordsParam));

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $columns = explode(',', $line);
            $callsign = trim(array_shift($columns));
            if ($callsign === '') {
                continue;
            }
            $records[] = new Record($callsign, $columns);
        }

        return $records;
    }

    /**
     * @param list<Record> $records
     */
    private static function hydratePersons(array $records): void
    {
        $callsigns = array_map(fn (Record $r) => $r->callsign, $records);
        $byNormalized = Person::findAllByCallsigns($callsigns);
        foreach ($records as $record) {
            $record->person = $byNormalized[Person::normalizeCallsign($record->callsign)] ?? null;
        }
    }

    private static function handlerFor(string $action): Handler
    {
        if (str_starts_with($action, 'cert-')) {
            return new CertificationHandler();
        }
        if (str_starts_with($action, 'alloc_')) {
            return new ProvisionHandler();
        }

        $key = self::ACTIONS[$action] ?? throw new UnacceptableConditionException('Unknown action');
        $class = self::HANDLERS[$key];
        return new $class();
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildResult(Record $record): array
    {
        if (!$record->person) {
            return ['status' => self::STATUS_CALLSIGN_NOT_FOUND, 'callsign' => $record->callsign];
        }

        $person = $record->person;
        $result = [
            'id' => $person->id,
            'callsign' => $person->callsign,
            'status' => $record->status,
        ];

        if (in_array($person->status, Person::LOCKED_STATUSES)) {
            $result['status'] = self::STATUS_FAILED;
            $result['details'] = "Account is locked due to status {$person->status}";
            return $result;
        }

        if ($record->changes) {
            $result['changes'] = $record->changes;
        }
        if ($record->details) {
            $result['details'] = $record->details;
        }
        return $result;
    }
}
