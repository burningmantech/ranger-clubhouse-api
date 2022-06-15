<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\ActionLog;
use App\Models\Bmid;
use App\Models\Certification;
use App\Models\Person;
use App\Models\PersonCertification;
use App\Models\PersonEvent;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class BulkUploader
{
    //
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_WARNING = 'warning';
    const STATUS_CALLSIGN_NOT_FOUND = 'callsign-not-found';

    const CHANGE_PERSON_COLUMN_ACTION = 'changePersonColumn';
    const CHANGE_EVENT_COLUMN_ACTION = 'changeEventColumn';
    const CHANGE_PERSON_STATUS_ACTION = 'changePersonStatus';
    const PROCESS_BMID_ACTION = 'processBmid';
    const PROCESS_PROVISIONS_ACTION = 'processProvisions';
    const PROCESS_TICKETS_ACTION = 'processTickets';
    const PROCESS_WAP_ACTION = 'processWAPs';
    const CERTIFICATION_ACTION = 'processCertifications';

    const HELP_CALLSIGN = 'callsign';
    const HELP_RADIO = 'callsign,radio count';
    const HELP_CERTIFICATION = "callsign,issued on date,card number,trained on date\ndate format = YYYY-MM-DD\nAll fields, other than the callsign, are optional and may be left blank.
    Examples: hubcap,,12345 to record the card number\nhubcap,2022-01-4,,2021-12-20 to record the issued on and trained on dates.";

    // Note: certification actions will be added by the BulkUploadControl actions method.

    const ACTION_DESCRIPTIONS = [
        [
            'label' => 'Earned Provisions Actions',
            'options' => [
                ['id' => AccessDocument::ALL_EAT_PASS, 'label' => 'Earned All Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => AccessDocument::EVENT_EAT_PASS, 'label' => 'Earned Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => AccessDocument::WET_SPOT, 'label' => 'Earned Wet Spot', 'help' => self::HELP_CALLSIGN],
                [
                    'id' => AccessDocument::EVENT_RADIO,
                    'label' => 'Earned Event Radio',
                    'help' => self::HELP_RADIO
                ]
            ]
        ],
        [
            'label' => 'Allocated Provisions Actions',
            'options' => [
                ['id' => 'alloc_' . AccessDocument::ALL_EAT_PASS, 'label' => 'Allocated All Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::EVENT_EAT_PASS, 'label' => 'Allocated Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::PRE_EVENT_EAT_PASS, 'label' => 'Allocated Pre-Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::PRE_EVENT_EVENT_EAT_PASS, 'label' => 'Allocated Pre-Event + Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::PRE_POST_EAT_PASS, 'label' => 'Allocated Pre+Post Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::EVENT_POST_EAT_PASS, 'label' => 'Allocated Event + Post Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::POST_EVENT_EAT_PASS, 'label' => 'Allocated Post-Event Eat Pass', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::WET_SPOT, 'label' => 'Allocated Wet Spot', 'help' => self::HELP_CALLSIGN],
                ['id' => 'alloc_' . AccessDocument::EVENT_RADIO, 'label' => 'Allocated Event Radio', 'help' => self::HELP_RADIO]
            ]
        ],
        [
            'label' => 'BMID Actions',
            'options' => [
                ['id' => 'meals', 'label' => 'Set meals on BMID',
                    'help' => "callsign,meals\nmeals = pre, post, event, all\nAdd plus (+) or minus (-) to add or subtract a meal period\n(e.g., hubcap,-event will remove the event week meal period",
                ],
                ['id' => 'showers', 'label' => 'Set showers on BMID',
                    'help' => "callsign,y/n/1/0"
                ],
                ['id' => 'bmidsubmitted', 'label' => 'Mark BMID as submitted', 'help' => self::HELP_CALLSIGN],
            ]
        ],
        [
            'label' => 'Ticket Actions',
            'options' => [
                [
                    'id' => 'tickets',
                    'label' => 'Create Access Documents',
                    'help' => "callsign,type[,wap date]\ntype = cred (Staff Credential), rpt (Reduced-Price Ticket), gift (Gift Ticket), vp (Vehicle Pass), wap (Work Access Pass)",
                ],
                [
                    'id' => 'wap',
                    'label' => 'Update WAP dates',
                    'help' => "callsign,date\ndate = YYYY-MM-DD or any (for anytime access)"
                ],
            ]
        ],
        [
            'label' => 'Vehicle Paperwork Flags',
            'options' => [
                ['id' => 'signed_motorpool_agreement', 'label' => 'Signed Motorpool Agreement (gators/golf carts)', 'help' => self::HELP_CALLSIGN],
                ['id' => 'org_vehicle_insurance', 'label' => 'Has Org Vehicle Insurance (MVR)', 'help' => self::HELP_CALLSIGN],
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

    ];

    const ACTIONS = [
        'vintage' => self::CHANGE_PERSON_COLUMN_ACTION,

        'org_vehicle_insurance' => self::CHANGE_EVENT_COLUMN_ACTION,
        'signed_motorpool_agreement' => self::CHANGE_EVENT_COLUMN_ACTION,
        'may_request_stickers' => self::CHANGE_EVENT_COLUMN_ACTION,
        'sandman_affidavit' => self::CHANGE_EVENT_COLUMN_ACTION,

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
    ];

    const MEALS_SORT = [
        Bmid::MEALS_PRE => 1,
        Bmid::MEALS_EVENT => 2,
        Bmid::MEALS_POST => 3,
    ];

    /**
     * Process a callsign list according to given action
     *
     * @param string $action - action as defined in self::ACTIONS
     * @param bool $commit - true if upload is to be committed to the database, otherwise just verify
     * @param string $reason - the reason the bulk upload is being done
     * @param string $recordsParam - a callsign list with parameters new line terminated
     */

    public static function process(string $action, bool $commit, string $reason, string $recordsParam): array
    {
        $lines = explode("\n", str_replace("\r", "", $recordsParam));

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
                throw new InvalidArgumentException('Unknown action');
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

    public static function changePersonStatus($records, $action, $commit, $reason)
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->status;
            $newValue = $person->status = $action;
            $person->auditReason = $reason;

            $record->status = self::STATUS_SUCCESS;
            if ($commit) {
                $person->changeStatus($newValue, $oldValue, $reason);
                self::saveModel($person, $record);
            }
            $record->changes = [$oldValue, $newValue];
        }
    }

    public static function processBmid($records, $action, $commit, $reason)
    {
        $year = current_year();

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $bmid = Bmid::findForPersonManage($person->id, $year);

            $data = $record->data;
            if ($action != 'bmidsubmitted' && !count($data)) {
                $record->status = 'failed';
                $record->details = ($action == 'showers') ? 'missing showers value (y,1,n,0)' : 'missing meal column';
                continue;
            }

            $reason = null;
            switch ($action) {
                case 'showers':
                    $showers = strtolower(trim($data[0]));
                    $oldValue = $bmid->showers;
                    $newValue = $bmid->showers = ($showers[0] == 'y' || $showers[0] == 1);
                    break;

                case 'meals':
                    $meals = trim($data[0]);
                    if ($meals[0] == "+") {
                        if ($bmid->meals == Bmid::MEALS_ALL) {
                            $meals = Bmid::MEALS_ALL;
                        } else {
                            $meals = substr($meals, 1, strlen($meals) - 1);
                            $matrix = [];
                            foreach (explode('+', $bmid->meals) as $week) {
                                // Deal with '+thing' which turns into [ "", "thing"]
                                if (!empty($week)) {
                                    $matrix[$week] = true;
                                }
                            }
                            $matrix[$meals] = true;
                            if (count($matrix) == 3) {
                                // Has all three weeks.
                                $meals = Bmid::MEALS_ALL;
                            } else {
                                // Sort week order (pre, event, post)
                                uksort($matrix, fn($a, $b) => (self::MEALS_SORT[$a] - self::MEALS_SORT[$b]));
                                $meals = implode('+', array_keys($matrix));
                            }
                        }
                    }
                    $oldValue = $bmid->meals;
                    $newValue = $bmid->meals = $meals;
                    break;

                case 'bmidsubmitted':
                    if ($bmid->status != "on_hold" && $bmid->status != "ready_to_print") {
                        $record->status = self::STATUS_FAILED;
                        $record->details = "BMID has status [{$bmid->status}] and cannot be submitted";
                        continue 2;
                    }

                    $oldValue = $bmid->status;
                    // TODO: used to be 'uploaded', yet the schema does not include that status.
                    $newValue = $bmid->status = 'submitted';
                    break;

                default:
                    throw new InvalidArgumentException('Unknown action');
                    break;
            }

            $record->status = self::STATUS_SUCCESS;
            $bmid->auditReason = $reason;
            if ($commit) {
                self::saveModel($bmid, $record);
            }

            $record->changes = [$oldValue, $newValue];
        }
    }

    public static function processTickets($records, $action, $commit, $reason)
    {
        $year = current_year();

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $data = $record->data;
            if (empty($data)) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'missing ticket type';
                continue;
            }

            $type = trim($data[0]);


            /* This assumes we're being run in January or later! */
            $sourceYear = $year - 1;
            $expiryYear = $year;

            /*
             * Tickets are good for three years.  If you earned a
             * ticket in 2016 for use in the 2017 event then:
             * 2017 is year 0
             * 2018 is year 1
             * 2019 is year 2
             * 2020 is year 3 ... and it expires AFTER the 2020 event.
             */

            if ($type == "CRED" || $type == "RPT" || $type == "GIFT") {
                $expiryYear = $year + 3;
            }

            $accessDate = null;
            switch (strtoupper($type)) {
                case 'CRED':
                    $type = AccessDocument::STAFF_CREDENTIAL;
                    if (count($data) >= 2) {
                        $accessDate = trim($data[1]);
                    }
                    break;

                case 'RPT':
                    $type = AccessDocument::RPT;
                    break;

                case 'GIFT':
                    $type = AccessDocument::GIFT;
                    break;

                case 'VP':
                    $type = AccessDocument::VEHICLE_PASS;
                    break;

                case 'WAP':
                    $type = AccessDocument::WAP;
                    if (count($data) >= 2) {
                        $accessDate = trim($data[1]);
                    }
                    break;

                default:
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Unknown ticket type [$type]";
                    continue 2;
            }

            if ($accessDate != null) {
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (Exception $e) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Access date is invalid [$accessDate]";
                    continue;
                }

                if ($accessDateCleaned->year < $year) {
                    $record->status = self::STATUS_FAILED;
                    $record->details = "Access date is before this year $year [$accessDate]";
                    continue;
                }
            }

            $uploadDate = date('n/j/y G:i:s');

            $ad = new AccessDocument(
                [
                    'person_id' => $person->id,
                    'type' => $type,
                    'source_year' => $sourceYear,
                    'expiry_date' => $expiryYear,
                    'comments' => "$uploadDate {self::user->callsign}: $reason",
                    'status' => AccessDocument::QUALIFIED,
                ]
            );

            if ($accessDate != null) {
                $ad->access_date = $accessDateCleaned;
            }

            $record->status = self::STATUS_SUCCESS;
            if ($commit) {
                if (self::saveModel($ad, $record)) {
                    AccessDocumentChanges::log($ad, Auth::user()->id, $ad, 'create');
                }
            }
        }
    }

    public static function processWAPs($records, $action, $commit, $reason)
    {
        $year = current_year();
        $low = 5;
        $high = 26;
        $wapDate = setting('TAS_WAPDateRange', true);
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
                $record->details = 'No WAP access document could be found';
            } elseif ($wap->status == AccessDocument::SUBMITTED) {
                $record->status = self::STATUS_FAILED;
                $record->details = 'WAP has already been submitted';
            } else {
                if ($anytime) {
                    $accessDate = null;
                    $accessAnyTime = true;
                } else {
                    $accessDate = $accessDateCleaned;
                    $accessAnyTime = false;
                }
                if ($commit) {
                    AccessDocument::updateWAPsForPerson($person->id, $accessDate, $accessAnyTime, 'set via bulk uploader');
                }
                $record->status = self::STATUS_SUCCESS;
            }
        }
    }

    public static function processProvisions($records, $type, $commit, $reason): void
    {
        $sourceYear = current_year();
        $expiryYear = $sourceYear + 3;

        $isAllocated = str_starts_with($type, 'alloc_');
        if ($isAllocated) {
            $type = str_replace('alloc_', '', $type);

        }
        if (!in_array($type, AccessDocument::PROVISION_TYPES)) {
            throw new InvalidArgumentException('Unknown provision type');
        }

        $isEventRadio = ($type == AccessDocument::EVENT_RADIO);
        if (in_array($type, AccessDocument::EAT_PASSES)) {
            $existingTypes = AccessDocument::EAT_PASSES;
        } else {
            $existingTypes = [$type];
        }

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }
            $personId = $record->person->id;
            $existing = null;

            $existing = AccessDocument::findAvailableTypeForPerson($personId, $existingTypes, $isAllocated);
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
            if (!$commit) {
                continue;
            }

            $ad = new AccessDocument([
                'person_id' => $person->id,
                'type' => $type,
                'status' => AccessDocument::QUALIFIED,
                'expiry_date' => $expiryYear,
                'source_year' => $sourceYear,
                'is_allocated' => $isAllocated,
            ]);

            if ($isEventRadio) {
                $ad->item_count = empty($record->data) ? 1 : (int)$record->data[0];
            }

            $ad->auditReason = 'created via bulk upload';
            self::saveModel($ad, $record);

            if (!$existing) {
                continue;
            }

            $existing->status = AccessDocument::CANCELLED;
            $existing->additional_comments = $existing->auditReason = 'Replaced by item #' . $ad->id . ' via bulk uploader';
            $record->status = self::STATUS_WARNING;
            $record->details = "Existing item #" . $existing->id . " " . $existing->getTypeLabel()
                . " cancelled and replaced with #" . $ad->id . " " . $ad->getTypeLabel();
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
}