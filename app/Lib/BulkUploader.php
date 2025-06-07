<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Models\AccessDocument;
use App\Models\ActionLog;
use App\Models\Bmid;
use App\Models\Certification;
use App\Models\Person;
use App\Models\PersonCertification;
use App\Models\PersonEvent;
use App\Models\PersonTeamLog;
use App\Models\Provision;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BulkUploader
{
    //
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

    // Meal pass combination (used to be old Provisions type before the conversion to the single Meal type)
    const string ALL_EAT_PASS = 'all_eat_pass';
    const string EVENT_EAT_PASS = 'event_eat_pass';
    const string PRE_EVENT_EAT_PASS = 'pre_event_eat_pass';
    const string POST_EVENT_EAT_PASS = 'post_event_eat_pass';
    const string PRE_EVENT_EVENT_EAT_PASS = 'pre_event_event_eat_pass';
    const string PRE_POST_EAT_PASS = 'pre_post_eat_pass';
    const string EVENT_POST_EAT_PASS = 'event_post_event_eat_pass';

    const array MEAL_TYPES = [
        self::ALL_EAT_PASS,
        self::EVENT_EAT_PASS,
        self::PRE_EVENT_EAT_PASS,
        self::POST_EVENT_EAT_PASS,
        self::PRE_EVENT_EVENT_EAT_PASS,
        self::EVENT_POST_EAT_PASS,
        self::PRE_POST_EAT_PASS,
    ];

    const array MEAL_MATRIX = [
        self::ALL_EAT_PASS => 'pre+event+post',
        self::EVENT_EAT_PASS => 'event',
        self::PRE_EVENT_EAT_PASS => 'pre',
        self::POST_EVENT_EAT_PASS => 'post',
        self::PRE_EVENT_EVENT_EAT_PASS => 'pre+event',
        self::EVENT_POST_EAT_PASS => 'event+post',
        self::PRE_POST_EAT_PASS => 'pre+post'
    ];


    // Note: certification actions will be added by the BulkUploadControl actions method.

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

    /**
     * Process a callsign list according to given action
     *
     * @param string $action - action as defined in self::ACTIONS
     * @param bool $commit - true if upload is to be committed to the database, otherwise just verify
     * @param string $reason - the reason the bulk upload is being done
     * @param string $recordsParam - a callsign list with parameters new line terminated
     * @throws UnacceptableConditionException
     */

    public static function process(string $action, bool $commit, string $reason, string $recordsParam): array
    {
        $lines = explode("\n", str_replace("\r", "", $recordsParam));

        $callsigns = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode(',', $line);
            $callsign = trim(array_shift($columns));
            if (empty($callsign)) {
                continue;
            }

            $records[] = (object)[
                'callsign' => $callsign,
                'data' => $columns,
                'id' => null,
                'person' => null,
                'status' => null,
                'details' => null,
                'changes' => null,
            ];

            $callsigns[] = $callsign;
        }

        if (empty($records)) {
            return [];
        }

        $callsigns = Person::findAllByCallsigns($callsigns);

        foreach ($records as $record) {
            $record->person = $callsigns[Person::normalizeCallsign($record->callsign)] ?? null;
        }

        if (str_starts_with($action, 'cert-')) {
            $processAction = self::CERTIFICATION_ACTION;
        } else if (str_starts_with($action, 'alloc_')) {
            $processAction = self::PROCESS_PROVISIONS_ACTION;
        } else {
            $processAction = self::ACTIONS[$action] ?? null;
            if (!$processAction) {
                throw new UnacceptableConditionException('Unknown action');
            }
        }

        self::$processAction($records, $action, $commit, $reason);

        $results = array_map(function ($record) {
            $person = $record->person;
            if (!$person) {
                return ['status' => self::STATUS_CALLSIGN_NOT_FOUND, 'callsign' => $record->callsign];
            }

            $result = [
                'id' => $record->person->id,
                'callsign' => $record->person->callsign,
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
        }, $records);

        if ($commit) {
            ActionLog::record(Auth::user(), 'bulk-upload', 'bulk upload commit',
                [
                    'action' => $action,
                    'reason' => $reason,
                    'records' => $recordsParam,
                    'results' => $results
                ]
            );
        }

        return $results;
    }

    /**
     * Attempt to save a row.
     *
     * @param $model
     * @param $record
     * @return bool
     */

    public static function saveModel($model, $record): bool
    {
        try {
            $model->saveWithoutValidation();
            return true;
        } catch (QueryException $e) {
            $record->status = self::STATUS_FAILED;
            $record->details = 'SQL Failure ' . $e->getMessage();
            return false;
        }
    }

    public static function changePersonColumn($records, $action, $commit, $reason): void
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->$action;
            $person->$action = 1;
            $record->status = self::STATUS_SUCCESS;

            if ($commit) {
                if (!self::saveModel($person, $record)) {
                    continue;
                }
            }
            $record->changes = [$oldValue, 1];
        }
    }

    public static function changeEventColumn($records, $action, $commit, $reason): void
    {
        $year = current_year();
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $event = PersonEvent::firstOrNewForPersonYear($person->id, $year);
            $oldValue = $event->$action;
            $event->$action = 1;
            $event->auditReason = $reason;

            $record->status = self::STATUS_SUCCESS;

            if ($commit) {
                if (!self::saveModel($event, $record)) {
                    continue;
                }
            }
            $record->changes = [$oldValue, 1];
        }
    }

    public static function changePersonStatus($records, $action, $commit, $reason): void
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->status;
            $newValue = $action;
            if ($commit) {
                $person->status = $action;
                $person->auditReason = $reason;
                self::saveModel($person, $record);
            }
            $record->status = self::STATUS_SUCCESS;
            $record->changes = [$oldValue, $newValue];
        }
    }

    public static function processBmid($records, $action, $commit, $reason): void
    {
        $year = current_year();

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $bmid = Bmid::findForPersonManage($person->id, $year);

            $reason = null;
            switch ($action) {
                case 'bmidsubmitted':
                    if ($bmid->status != Bmid::ISSUES && $bmid->status != Bmid::READY_TO_PRINT) {
                        $record->status = self::STATUS_FAILED;
                        $record->details = "BMID has status [{$bmid->status}] and cannot be submitted";
                        continue 2;
                    }

                    $oldValue = $bmid->status;
                    $newValue = $bmid->status = Bmid::SUBMITTED;
                    break;

                default:
                    throw new UnacceptableConditionException('Unknown action');
            }

            $record->status = self::STATUS_SUCCESS;
            $bmid->auditReason = $reason;
            if ($commit) {
                self::saveModel($bmid, $record);
            }

            $record->changes = [$oldValue, $newValue];
        }
    }

    /**
     * Process ticket actions (create Staff Credentials, Special Price Tickets, etc)
     *
     * @param $records
     * @param $action
     * @param $commit
     * @param $reason
     * @return void
     */

    public static function processTickets($records, $action, $commit, $reason): void
    {
        list ($defaultSourceYear, $defaultExpiryYear) = self::defaultYears(false);
        $year = current_year();

        $callsign = Auth::user()?->callsign ?? "unknown";

        $saps = AccessDocument::where(function ($w) {
            // Find any SAPs, or SC's with an access specification.
            $w->where('type', AccessDocument::WAP);
            $w->orWhere(function ($sc) {
                $sc->where('type', AccessDocument::STAFF_CREDENTIAL);
                $sc->where(function ($access) {
                    $access->where('access_any_time', true);
                    $access->orWhereNotNull('access_date');
                });
            });
        })->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->get()
            ->groupBy('person_id');

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $nextInd = 0;
            $data = $record->data;
            $dataCount = count($data);
            if (empty($data)) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'missing ticket type';
                continue;
            }

            $type = trim($data[$nextInd++]);

            $sourceYear = $defaultSourceYear;

            if ($type == "CRED" || $type == "SPT" || $type == "GIFT") {
                $expiryYear = $defaultExpiryYear;
            } else {
                $expiryYear = $year;
            }

            $accessDate = null;

            switch (strtoupper($type)) {
                case 'CRED':
                    $type = AccessDocument::STAFF_CREDENTIAL;
                    break;

                case 'SPT':
                    $type = AccessDocument::SPT;
                    break;

                case 'LSD':
                    $sourceYear = $year;
                    $type = AccessDocument::LSD;
                    break;

                case 'GIFT':
                    $sourceYear = $year;
                    $type = AccessDocument::GIFT;
                    break;

                case 'VP':
                    $type = AccessDocument::VEHICLE_PASS_SP;
                    break;

                case 'VPGIFT':
                    $sourceYear = $year;
                    $type = AccessDocument::VEHICLE_PASS_GIFT;
                    break;

                case 'VPLSD':
                    $sourceYear = $year;
                    $type = AccessDocument::VEHICLE_PASS_LSD;
                    break;

                case 'WAP':
                case 'SAP':
                    $type = AccessDocument::WAP;
                    $sourceYear = $year;
                    break;

                default:
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Unknown ticket type [$type]";
                    continue 2;
            }

            // Optionally pop an access date if this type supports them
            if ($type == AccessDocument::STAFF_CREDENTIAL || $type == AccessDocument::WAP) {
                if ($dataCount >= $nextInd + 1) {
                    $accessDate = trim($data[$nextInd++]);
                }
            }
            // Optionally pop a source year if this type supports them
            if (in_array($type, AccessDocument::TICKET_TYPES)) {
                if ($dataCount >= $nextInd + 1) {
                    if (!self::checkYearRange($sourceYear, $data[$nextInd++], $record, true)) {
                        continue;
                    }
                }
            }

            if ($type == AccessDocument::SPT || $type == AccessDocument::STAFF_CREDENTIAL) {
                // Optionally pop an expiry year if this type supports them
                if ($dataCount >= $nextInd + 1) {
                    if (!self::checkYearRange($expiryYear, $data[$nextInd++], $record, false)) {
                        continue;
                    }
                }
            }

            // Fail if there are additional unconsumed data columns
            if ($dataCount != $nextInd) {
                $record->status = self::STATUS_FAILED;
                $record->details = "Unexpected additional data for $type. See the required line format";
                continue;
            }

            if ($expiryYear < $sourceYear) {
                $record->status = self::STATUS_FAILED;
                $record->details = "Source year [$sourceYear] must not be after expiry year [$expiryYear]";
                continue;
            }

            if ($accessDate != null) {
                // A date can't reasonably be specified in four or fewer characters.
                // This is basically a protection against someone putting in a year rather than a date,
                // since Carbon happily misinterprets YYYY as today at time YY:YY:00.
                if (strlen($accessDate) <= 4) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Access date is invalid. Try YYYY-MM-DD format. [$accessDate]";
                    continue;
                }
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (Exception) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Access date is invalid. Try YYYY-MM-DD format. [$accessDate]";
                    continue;
                }

                if ($accessDateCleaned->year < $year) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Access date is before this year $year [$accessDate]";
                    continue;
                }
            }


            $uploadDate = date('n/j/y G:i:s');

            if ($type == AccessDocument::WAP) {
                // Ensure the SAP's date does not attempt to replace a SC/WAP with any earlier access date.
                $ads = $saps->get($person->id);
                if ($ads) {
                    $sap = AccessDocument::wapCandidate($ads);
                    if ($sap) {
                        if ($sap->type == AccessDocument::STAFF_CREDENTIAL) {
                            if ($sap->access_any_time) {
                                $record->status = self::STATUS_FAILED;
                                $record->details = "Staff Credential RAD-{$sap->id} status {$sap->status} exists with any time access";
                                continue;
                            } else if ($sap->access_date->lt($accessDate)) {
                                $record->status = self::STATUS_FAILED;
                                $record->details = "Staff Credential RAD-{$sap->id} status {$sap->status} has an early access date of " . ((string)$sap->access_date);
                                continue;
                            }
                        } else if ($sap->access_date->lt($accessDate)) {
                            $record->status = self::STATUS_FAILED;
                            $record->details = "SAP RAD-{$sap->id} status {$sap->status} has an early access date of " . ((string)$sap->access_date);
                            continue;
                        }
                    }
                }
            }

            $ad = new AccessDocument([
                'person_id' => $person->id,
                'type' => $type,
                'source_year' => $sourceYear,
                'expiry_date' => $expiryYear,
                'comments' => "$uploadDate {$callsign}: $reason",
                'status' => AccessDocument::QUALIFIED,
            ]);

            if ($accessDate != null) {
                $ad->access_date = $accessDateCleaned;
            }

            $record->status = self::STATUS_SUCCESS;
            if ($commit) {
                self::saveModel($ad, $record);
            }
        }
    }

    /**
     * Process WAP actions
     *
     * @param $records
     * @param $action
     * @param $commit
     * @param $reason
     * @return void
     */

    public static function processSAPs($records, $action, $commit, $reason): void
    {
        $year = current_year();
        $low = 5;
        $high = 26;
        $wapDate = setting('TAS_SAPDateRange', true);
        if (!empty($wapDate)) {
            list($low, $high) = explode("-", $wapDate);
        }

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $data = $record->data;
            if (empty($data)) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'missing access type';
            }

            $accessDate = trim($data[0]);

            $anytime = false;
            if (strtolower($accessDate) == 'any') {
                $anytime = true;
            } else {
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (InvalidFormatException $e) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Invalid date [$accessDate]";
                    continue;
                }

                if ($accessDateCleaned->year != $year
                    || $accessDateCleaned->month != 8
                    || $accessDateCleaned->day < $low
                    || $accessDateCleaned->day > $high) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Date is outside of $year-08-$low and 08-$high";
                    continue;
                }
            }

            $wap = AccessDocument::findWAPForPerson($person->id);

            if ($wap == null) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'No SAP access document could be found';
            } elseif ($wap->status == AccessDocument::SUBMITTED) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'SAP has already been submitted';
            } else {
                if ($anytime) {
                    $accessDate = null;
                    $accessAnyTime = true;
                } else {
                    $accessDate = $accessDateCleaned;
                    $accessAnyTime = false;
                    if ($wap->type === AccessDocument::STAFF_CREDENTIAL && $wap->access_any_time) {
                        $record->status = self::STATUS_FAILED;
                        $record->details = "Staff Credential RAD-{$wap->id} status {$wap->status} will be cleared of access any time.";
                        continue;
                    }

                    if ($wap->access_date?->lt($accessDateCleaned)) {
                        $record->status = self::STATUS_FAILED;
                        $record->details = "SAP RAD-{$wap->id} will access date {$wap->access_date->format('Y-m-d')} be updated with a later access date {$wap->access_date->format('Y-m-d')}";
                        continue;
                    }
                }
                if ($commit) {
                    AccessDocument::updateSAPsForPerson($person->id, $accessDate, $accessAnyTime, 'set via bulk uploader');
                }
                $record->status = self::STATUS_SUCCESS;
            }
        }
    }

    /**
     * Process provision uploads
     *
     * @param $records
     * @param $type
     * @param $commit
     * @param $reason
     * @return void
     * @throws UnacceptableConditionException
     */

    public static function processProvisions($records, $type, $commit, $reason): void
    {
        list ($defaultSourceYear, $defaultExpiryYear) = self::defaultYears(true);

        $year = current_year();

        $isAllocated = str_starts_with($type, 'alloc_');
        if ($isAllocated) {
            $type = str_replace('alloc_', '', $type);
            $defaultSourceYear = $year;
        }

        if ($type != Provision::WET_SPOT && $type != Provision::EVENT_RADIO && !in_array($type, self::MEAL_TYPES)) {
            throw new UnacceptableConditionException('Unknown provision type');
        }

        $isEventRadio = ($type == Provision::EVENT_RADIO);
        $isMeals = false;
        $preMeals = false;
        $postMeals = false;
        $eventMeals = false;

        if (in_array($type, self::MEAL_TYPES)) {
            $existingType = Provision::MEALS;
            $isMeals = true;
            $periods = self::MEAL_MATRIX[$type] ?? '';
            foreach (explode('+', $periods) as $period) {
                switch ($period) {
                    case 'pre':
                        $preMeals = true;
                        break;
                    case 'post':
                        $postMeals = true;
                        break;
                    case 'event':
                        $eventMeals = true;
                        break;
                    default:
                        throw new UnacceptableConditionException("Unknown period [$period] for type [$type]");
                }
            }
        } else {
            $existingType = $type;
        }

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }
            $personId = $record->person->id;
            $existing = null;

            if ($isMeals) {
                $existing = Provision::findAvailableMealsForPerson($personId, $isAllocated, $preMeals, $eventMeals, $postMeals);
            } else {
                $existing = Provision::findAvailableTypeForPerson($personId, $existingType, $isAllocated);
            }
            if ($existing && !$commit) {
                $record->status = self::STATUS_WARNING;
                if ($isAllocated) {
                    $record->details = 'Already has ' . $existing->getTypeLabel() . ' allocated provision. Existing item will be cancelled and replaced.';
                } else {
                    $record->details = 'Has ' . $existing->getTypeLabel() . ' earned year ' . $existing->source_year . '. Existing item will be cancelled and replaced.';
                }
                continue;
            }

            $record->status = self::STATUS_SUCCESS;
            $sourceYear = $defaultSourceYear;
            $expiryYear = $defaultExpiryYear;
            $itemCount = 0;

            $data = $record->data;
            $fieldCount = count($data);

            if ($isEventRadio) {
                if ($fieldCount) {
                    $itemCount = array_shift($data);
                    if (!is_numeric($itemCount)) {
                        $record->status = self::STATUS_FAILED;
                        $record->details = 'Item count is not a number';
                        continue;
                    }
                    $itemCount = (int)$itemCount;
                    $fieldCount--;
                } else {
                    $itemCount = 1;
                }
            }

            if ($isAllocated) {
                if ($fieldCount >= 1) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = 'Allocated provisions uploads only take a callsign and no other parameters.';
                    continue;
                }
            } else {
                if ($fieldCount >= 1) {
                    if (!self::checkYearRange($sourceYear, $data[0], $record, true)) {
                        continue;
                    }
                }

                if ($fieldCount >= 2) {
                    if (!self::checkYearRange($expiryYear, $data[1], $record, false)) {
                        continue;
                    }
                }
            }

            if ($expiryYear < $sourceYear) {
                $record->status = self::STATUS_FAILED;
                $record->details = "Source year [$sourceYear] is after expiry year [$expiryYear]";
                continue;
            }

            if (!$commit) {
                continue;
            }

            $provision = new Provision([
                'person_id' => $person->id,
                'type' => $isMeals ? Provision::MEALS : $type,
                'status' => Provision::AVAILABLE,
                'expires_on' => $expiryYear,
                'source_year' => $sourceYear,
                'is_allocated' => $isAllocated,
                'additional_comments' => 'created via bulk uploader'
            ]);

            if ($isEventRadio) {
                $provision->item_count = $itemCount;
            } else if ($isMeals) {
                $provision->pre_event_meals = $preMeals;
                $provision->event_week_meals = $eventMeals;
                $provision->post_event_meals = $postMeals;
            }

            $provision->auditReason = 'created via bulk upload';
            self::saveModel($provision, $record);

            if (!$existing) {
                continue;
            }

            $existing->status = Provision::CANCELLED;
            $existing->additional_comments = $existing->auditReason = 'Replaced by item #' . $provision->id . ' via bulk uploader';
            $record->status = self::STATUS_WARNING;
            $record->details = "Existing provision RP-" . $existing->id . " " . $existing->getTypeLabel()
                . " cancelled and replaced with RP-" . $provision->id . " " . $provision->getTypeLabel();
            $existing->saveWithoutValidation();
        }
    }

    /**
     * Process uploading certification records
     *
     * @param $records
     * @param $type
     * @param $commit
     * @param $reason
     * @return void
     */

    public static function processCertifications($records, $type, $commit, $reason): void
    {
        $id = str_replace('cert-', '', $type);

        $cert = Certification::find($id);
        if (!$cert) {
            throw new RuntimeException("Certification ID [$id] for bulk uploading cannot be found?!?");
        }

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $record->status = self::STATUS_SUCCESS; // assume success

            $data = $record->data;
            $fieldCount = count($data);
            $cardNumber = null;
            $trainedOn = null;
            $issuedOn = null;

            switch ($fieldCount) {
                case 3:
                    $trainedOn = $data[2];
                    if (!self::dateIsValid($trainedOn)) {
                        $record->status = self::STATUS_FAILED;
                        $record->details = 'Invalid trained on date';
                    }
                // fall-thru
                case 2:
                    $cardNumber = $data[1];
                // fall-thru
                case 1:
                    $issuedOn = $data[0];
                    if (!self::dateIsValid($issuedOn)) {
                        $record->status = self::STATUS_FAILED;
                        $record->details = 'Invalid issued on date';
                    }
                    break;
            }

            $personId = $record->person->id;
            $pc = PersonCertification::findCertificationForPerson($cert->id, $personId);
            if ($pc && $record->status == self::STATUS_SUCCESS) {
                $fields = [];
                if (!empty($cardNumber)) {
                    $fields[] = 'Card number';
                }

                if (!empty($trainedOn)) {
                    $fields[] = 'Trained on date';
                }

                if (!empty($issuedOn)) {
                    $fields[] = 'Issued on date';
                }

                $record->details = 'Certification already exists. ';
                if (empty($fields)) {
                    $record->details .= 'No record fields will be updated.';
                } else {
                    $record->details .= implode(", ", $fields) . " will be updated.";
                }
                $record->status = self::STATUS_WARNING;
            }

            if (!$commit) {
                continue;
            }

            if (!$pc) {
                $pc = new PersonCertification;
                $pc->person_id = $personId;
                $pc->certification_id = $cert->id;
                $pc->recorder_id = Auth::id();
            }

            if (!empty($cardNumber)) {
                $pc->card_number = $cardNumber;
            }

            if (!empty($trainedOn)) {
                $pc->trained_on = $trainedOn;
            }

            if (!empty($issuedOn)) {
                $pc->issued_on = $issuedOn;
            }


            $pc->auditReason = $reason;
            $pc->saveWithoutValidation();
            $record->status = self::STATUS_SUCCESS;
        }
    }

    /**
     * If the date is non-blank, see if it's valid.
     *
     * @param string|null $date
     * @return bool
     */

    public static function dateIsValid(string|null $date): bool
    {
        if (empty($date)) {
            return true;
        }

        try {
            Carbon::parse($date);
            return true; // looks good
        } catch (InvalidFormatException) {
            return false; // wah, wah.
        }
    }

    /**
     * Process team membership. Format is:
     *
     * callsign,team name,joined date[,left date]
     *
     * date format is YYYY-MM-DD (no time of day)
     *
     * @param $records
     * @param $type
     * @param $commit
     * @param $reason
     * @throws ValidationException
     */

    public static function processTeamMembership($records, $type, $commit, $reason): void
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                // person not found -- skip it.
                continue;
            }

            $data = $record->data;
            if (empty($data)) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'missing team membership info';
                continue;
            }

            $teamName = trim($data[0]);
            if (empty($teamName)) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'missing team name';
                continue;
            }

            $team = Team::findByTitle($teamName);
            if (!$team) {
                $record->status = self::STATUS_FAILED;
                $record->details = "Team '$teamName' not found";
                continue;
            }

            if (empty($data[1])) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'missing joined on date';
                continue;
            } else {
                try {
                    $joinedOn = Carbon::parse($data[1]);
                } catch (InvalidFormatException $exception) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = 'Invalid joined on date';
                    continue;
                }
            }

            if (!empty($data[2])) {
                try {
                    $leftOn = Carbon::parse($data[2]);
                } catch (InvalidFormatException $exception) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = 'Invalid left on date';
                    continue;
                }
            } else {
                $leftOn = null;
            }

            if ($record->status == self::STATUS_FAILED) {
                continue;
            }

            if ($commit) {
                $history = new PersonTeamLog([
                    'person_id' => $person->id,
                    'team_id' => $team->id,
                    'joined_on' => $joinedOn,
                    'left_on' => $leftOn
                ]);

                $history->save();
            }

            $record->status = self::STATUS_SUCCESS;
        }
    }

    /**
     * Obtain the default source year. If we're running in September or later, default to the current year,
     * otherwise it's assumed the source year was last year.
     *
     *  Tickets are good for three years after the year it is intended to be used.
     *  If you earned a ticket in 2016 for use in the 2017 event then:
     *  2017 is year 0
     *  2018 is year 1
     *  2019 is year 2
     *  2020 is year 3 ... and it expires AFTER the 2020 event.
     *
     * Provisions expire 3 years after the year earned.
     *
     * @param bool $isProvision
     * @return array
     */

    public static function defaultYears(bool $isProvision): array
    {
        $now = now();
        if ($now->month >= 9) {
            return [$now->year, $now->year + ($isProvision ? 3 : 4)];
        } else {
            return [$now->year - 1, $now->year + ($isProvision ? 2 : 3)];
        }
    }

    /**
     * Verify the year make sense. Must be a number, and outside of a -/+ 5 year range.
     *
     * @param $year
     * @param $input
     * @param $record
     * @param string $label
     * @return bool
     */

    public static function checkYearRange(&$year, $input, $record, bool $isSource = false): bool
    {
        $label = $isSource ? "Source Year" : "Expiry Year";

        $input = trim($input);
        if (!is_numeric($input)) {
            $record->status = self::STATUS_FAILED;
            $record->details = "$label is not a number";
            return false;
        }

        $year = (int)$input;
        $currentYear = current_year();
        if ($year < ($currentYear - 5)) {
            $record->status = self::STATUS_FAILED;
            $record->details = "$label is more than 5 years in the past";
            return false;
        }

        if ($isSource && $year > $currentYear) {
            $record->status = self::STATUS_FAILED;
            $record->details = "$label is in the future [$year]";
            return false;
        }

        if ($year > ($currentYear + 5)) {
            $record->status = self::STATUS_FAILED;
            $record->details = "$label is more than 5 years in the future";
            return false;
        }

        return true;
    }
}