<?php

namespace App\Http\Controllers;

use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Database\QueryException;
use App\Http\Controllers\ApiController;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\PersonEvent;

use Carbon\Carbon;
use InvalidArgumentException;

class BulkUploadController extends ApiController
{
    const SET_COLUMN_UPDATE_ACTIONS = [
        "vintage",
        "osha10",
        "osha30",
    ];

    const SET_EVENT_COLUMN_ACTIONS = [
        // Columns to be set to 1/true
        "org_vehicle_insurance",
        "signed_motorpool_agreement",
        "may_request_stickers",
        "sandman_affidavit"
    ];

    const STATUS_UPDATE_ACTIONS = [
        "active",
        "alpha",
        "inactive",
        "past prospective",
        "prospective waitlist",
        "prospective",
        "retired"
    ];

    const BMID_ACTIONS = [
        "meals",
        "showers",
        "bmidsubmitted",
    ];

    const TICKET_ACTIONS = [
        "tickets",
        "wap",
    ];

    const EVENT_ACTIONS = [
        "eventradio"
    ];

    const MAP_PRE_MEALS = [
        '' => 'pre',
        'pre' => 'pre',
        'post' => 'pre+post',
        'event' => 'pre+event',
        'pre+event' => 'pre+event',
        'event+post' => 'all',
        'pre+post' => 'pre+post',
        'all' => 'all'
    ];

    const MAP_EVENT_MEALS = [
        '' => 'event',
        'pre' => 'pre+event',
        'post' => 'event+post',
        'event' => 'event',
        'pre+event' => 'pre+event',
        'event+post' => 'event+post',
        'pre+post' => 'all',
        'all' => 'all'
    ];

    const MAP_POST_MEALS = [
        '' => 'post',
        'pre' => 'pre+post',
        'post' => 'post',
        'event' => 'event+post',
        'pre+event' => 'all',
        'event+post' => 'event+post',
        'pre+post' => 'pre+post',
        'all' => 'all'
    ];

    public function update()
    {
        $params = request()->validate([
            'action' => 'required|string',
            'records' => 'required|string',
            'commit' => 'sometimes|boolean',
            'reason' => 'sometimes|string',
        ]);

        $this->authorize('isAdmin');

        $action = $params['action'];
        $commit = $params['commit'] ?? false;
        $reason = $params['reason'] ?? 'bulk upload';
        $recordsParam = $params['records'];


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
            throw new InvalidArgumentException('records parameter is empty');
        }

        $callsigns = Person::findAllByCallsigns($callsigns, true);

        foreach ($records as $record) {
            $record->person = @$callsigns[strtolower($record->callsign)];
        }

        if (in_array($action, self::SET_COLUMN_UPDATE_ACTIONS)) {
            $this->changePersonColumn($records, $action, $commit, $reason);
        } else if (in_array($action, self::SET_EVENT_COLUMN_ACTIONS)) {
            $this->changeEventColumn($records, $action, $commit, $reason);
        } elseif (in_array($action, self::STATUS_UPDATE_ACTIONS)) {
            $this->changePersonStatus($records, $action, $commit, $reason);
        } elseif (in_array($action, self::BMID_ACTIONS)) {
            $this->processBmid($records, $action, $commit, $reason);
        } elseif ($action == 'tickets') {
            $this->processTickets($records, $action, $commit, $reason);
        } elseif ($action == 'wap') {
            $this->processWAPs($records, $action, $commit, $reason);
        } elseif ($action == 'eventradio') {
            $this->processEventRadio($records, $action, $commit, $reason);
        } else {
            throw new InvalidArgumentException('Unknown action');
        }

        $results = array_map(function ($record) {
            $person = $record->person;
            if (!$person) {
                return ['status' => 'callsign-not-found', 'callsign' => $record->callsign];
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
            $this->log('bulk-upload', 'bulk upload commit', [
                'action' => $action,
                'reason' => $reason,
                'records' => $recordsParam,
                'results' => $results
            ]);
        }

        return response()->json(['results' => $results, 'commit' => $commit ? true : false]);
    }

    private function changePersonColumn($records, $action, $commit, $reason)
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->$action;
            $person->$action = 1;
            $record->status = 'success';

            if ($commit) {
                if (!$this->saveModel($person, $record)) {
                    continue;
                }
            }
            $record->changes = [$oldValue, 1];
        }
    }

    private function changeEventColumn($records, $action, $commit, $reason)
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

            $record->status = 'success';

            if ($commit) {
                if (!$this->saveModel($event, $record)) {
                    continue;
                }
            }
            $record->changes = [$oldValue, 1];
        }
    }


    private function saveModel($model, $record)
    {
        try {
            $model->saveWithoutValidation();
            return true;
        } catch (QueryException $e) {
            $record->status = 'failed';
            $record->details = 'SQL Failure ' . $e->getMessage();
            return false;
        }
    }

    private function changePersonStatus($records, $action, $commit, $reason)
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->status;
            $newValue = $person->status = $action;
            $person->auditReason = $reason;

            $record->status = 'success';
            if ($commit) {
                $person->changeStatus($newValue, $oldValue, $reason);
                $this->saveModel($person, $record);
            }
            $record->changes = [$oldValue, $newValue];
        }
    }

    private function processBmid($records, $action, $commit, $reason)
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
                    if ($commit) {
                        $ad = AccessDocument::findAvailableTypeForPerson($person->id, AccessDocument::WET_SPOT);
                        if ($ad) {
                            if ($newValue && $ad->status != AccessDocument::CLAIMED) {
                                $ad->status = AccessDocument::CLAIMED;
                                $reason = 'claimed via set showers bulk uploader';
                            } else if (!$newValue && $ad->status == AccessDocument::CLAIMED) {
                                $ad->status = AccessDocument::BANKED;
                                $reason = 'banked via set showers bulk uploader';
                            }
                        } else if ($newValue) {
                            $ad = new AccessDocument([
                                'person_id' => $person->id,
                                'type' => AccessDocument::WET_SPOT,
                                'status' => AccessDocument::CLAIMED,
                                'expiry_date' => $year + 3,
                                'source_year' => $year,
                            ]);
                            $reason = 'claimed via set showers bulk upload';
                        }

                        if ($ad && $ad->isDirty()) {
                            $ad->additional_comments = $ad->auditReason = $reason;
                            $ad->saveWithoutValidation();
                        }
                    }
                    break;

                case 'meals':
                    $meals = trim($data[0]);
                    if ($meals[0] == "+") {
                        $meals = substr($meals, 1, strlen($meals) - 1);
                        if ($meals == "pre") {
                            $meals = self::MAP_PRE_MEALS[$bmid->meals];
                        } elseif ($meals == "event") {
                            $meals = self::MAP_EVENT_MEALS[$bmid->meals];
                        } elseif ($meals == "post") {
                            $meals = self::MAP_POST_MEALS[$bmid->meals];
                        }
                    }

                    $oldValue = $bmid->meals;
                    $newValue = $bmid->meals = $meals;

                    if ($commit) {
                        $ad = AccessDocument::findAvailableTypeForPerson($person->id, AccessDocument::ALL_YOU_CAN_EAT);
                        $isAllYouCanEat = ($newValue == Bmid::MEALS_ALL);
                        if ($ad) {
                            if (!$isAllYouCanEat && $ad->status != AccessDocument::CLAIMED) {
                                $ad->status = AccessDocument::BANKED;
                                $reason = 'banked via set meals bulk uploader';
                            } else if ($isAllYouCanEat && $ad->status != AccessDocument::CLAIMED) {
                                $ad->status = AccessDocument::CLAIMED;
                                $reason = 'claimed via set meals bulk uploader';
                            }
                        } else if ($isAllYouCanEat) {
                            $ad = new AccessDocument([
                                'person_id' => $person->id,
                                'type' => AccessDocument::ALL_YOU_CAN_EAT,
                                'status' => AccessDocument::CLAIMED,
                                'expiry_date' => $year + 3,
                                'source_year' => $year,
                            ]);
                            $reason = 'claimed via bulk upload';
                        }

                        if ($ad && $ad->isDirty()) {
                            $ad->additional_comments = $ad->auditReason = $reason;
                            $ad->saveWithoutValidation();
                        }
                    }
                    break;

                case 'bmidsubmitted':
                    if ($bmid->status != "on_hold" && $bmid->status != "ready_to_print") {
                        $record->status = 'invalid-status';
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

            $record->status = 'success';
            $bmid->auditReason = $reason;
            if ($commit) {
                $this->saveModel($bmid, $record);
            }

            $record->changes = [$oldValue, $newValue];
        }
    }

    private function processTickets($records, $action, $commit, $reason)
    {
        $year = current_year();

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $data = $record->data;
            if (empty($data)) {
                $record->status = 'failed';
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
                    $record->status = 'failed';
                    $record->details = "Unknown ticket type [$type]";
                    continue 2;
            }

            if ($accessDate != null) {
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (Exception $e) {
                    $record->status = 'failed';
                    $record->details = "Access date is invalid [$accessDate]";
                    continue;
                }

                if ($accessDateCleaned->year < $year) {
                    $record->status = 'failed';
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
                    'comments' => "$uploadDate {$this->user->callsign}: $reason",
                    'status' => AccessDocument::QUALIFIED,
                ]
            );

            if ($accessDate != null) {
                $ad->access_date = $accessDateCleaned;
            }

            $record->status = 'success';
            if ($commit) {
                if ($this->saveModel($ad, $record)) {
                    AccessDocumentChanges::log($ad, $this->user->id, $ad, 'create');
                }
            }
        }
    }

    private function processWAPs($records, $action, $commit, $reason)
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
                $record->status = 'failed';
                $record->detail = 'missing access type';
            }

            $accessDate = trim($data[0]);

            $anytime = false;
            if (strtolower($accessDate) == 'any') {
                $anytime = true;
            } else {
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (InvalidFormatException $e) {
                    $record->status = 'failed';
                    $record->details = "Invalid date [$accessDate]";
                    continue;
                }

                if ($accessDateCleaned->year != $year
                    || $accessDateCleaned->month != 8
                    || $accessDateCleaned->day < $low
                    || $accessDateCleaned->day > $high) {
                    $record->status = 'failed';
                    $record->details = "Date is outside of $year-08-$low and 08-$high";
                    continue;
                }
            }

            $wap = AccessDocument::findWAPForPerson($person->id);

            if ($wap == null) {
                $record->status = 'failed';
                $record->details = 'No WAP access document could be found';
            } elseif ($wap->status == AccessDocument::SUBMITTED) {
                $record->status = 'failed';
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
                $record->status = 'success';
            }
        }
    }

    private function processEventRadio($records, $action, $commit, $reason)
    {
        $year = current_year();
        $uploadDate = date('n/j/y G:i:s');

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            if (!$commit) {
                // No other verification to do other than say yah the callsign exists.
                $record->status = 'success';
                continue;
            }

            $radio = AccessDocument::findAvailableTypeForPerson($person->id, AccessDocument::EVENT_RADIO);
            if (!$radio) {
                $radio = new AccessDocument([
                    'person_id' => $person->id,
                    'type' => AccessDocument::EVENT_RADIO,
                    'status' => AccessDocument::QUALIFIED,
                    'source_year' => $year,
                    'expiry_date' => $year,
                ]);
            }

            $radio->item_count = empty($record->data) ? 1 : (int)$record->data[0];
            $radio->additional_comments = "set by bulk upload";
            $this->saveModel($radio, $record);
            $record->status = 'success';
        }
    }
}
