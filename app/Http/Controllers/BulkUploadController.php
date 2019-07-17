<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Role;
use App\Models\Person;
use App\Models\RadioEligible;

use Carbon\Carbon;

class BulkUploadController extends ApiController
{
    const SET_COLUMN_UPDATE_ACTIONS = [
        // Columns to be set to 1/true
        "vehicle_insurance_paperwork",
        "vehicle_paperwork",
        "vintage",
        "osha10",
        "osha30",
        "sandman_affidavit"
    ];

    const STATUS_UPDATE_ACTIONS = [
        "active",
        "alpha",
        "inactive",
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
            'action'    => 'required|string',
            'records'   => 'required|string',
            'commit'    => 'sometimes|boolean',
        ]);

        if (!$this->userHasRole(Role::ADMIN)) {
            $this->notPermitted('User must have the Admin role.');
        }

        $action = $params['action'];
        $commit = @$params['commit'];

        $lines = explode("\n", str_replace("\r", "", $params['records']));

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $columns = explode(',', $line);
            $callsign = trim(array_shift($columns));
            if (empty($callsign)) {
                continue;
            }

            $records[] = (object) [
                'callsign'  => $callsign,
                'data'      => $columns,
                'id'        => null,
                'person'    => null,
                'status'    => null,
                'details'   => null,
                'changes'    => null,
            ];

            $callsigns[] = $callsign;
        }

        if (empty($records)) {
            throw new \InvalidArgumentException('records parameter is empty');
        }

        $callsigns = Person::findAllByCallsigns($callsigns, true);

        foreach ($records as $record) {
            $record->person = @$callsigns[strtolower($record->callsign)];
        }

        if (in_array($action, self::SET_COLUMN_UPDATE_ACTIONS)) {
            $this->changePersonColumn($records, $action, $commit);
        } elseif (in_array($action, self::STATUS_UPDATE_ACTIONS)) {
            $this->changePersonStatus($records, $action, $commit);
        } elseif (in_array($action, self::BMID_ACTIONS)) {
            $this->processBmid($records, $action, $commit);
        } elseif ($action == 'tickets') {
            $this->processTickets($records, $action, $commit);
        } elseif ($action == 'wap') {
            $this->processWAPs($records, $action, $commit);
        } elseif ($action == 'eventradio') {
            $this->processEventRadio($records, $action, $commit);
        } else {
            throw new \InvalidArgumentException('Unknown action');
        }

        $results = array_map(function ($record) {
            $person = $record->person;
            if (!$person) {
                return [ 'status' => 'callsign-not-found', 'callsign' => $record->callsign ];
            }

            $result = [
                'id'        => $record->person->id,
                'callsign'  => $record->person->callsign,
                'status'    => $record->status,
            ];

            if ($record->changes) {
                $result['changes'] = $record->changes;
            }

            if ($record->details) {
                $result['details'] = $record->details;
            }
            return $result;
        }, $records);

        return response()->json([ 'results' => $results, 'commit' => $commit ? true : false ]);
    }

    private function changePersonStatus($records, $action, $commit)
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->status;
            $newValue = $person->status = $action;
            $record->status = 'success';
            if ($commit) {
                if ($this->saveModel($person, $record)) {
                    $person->changeStatus($person->status, $action, 'bulk update');
                } else {
                    continue;
                }
            }
            $record->changes = [ $oldValue, $newValue ];
        }
    }

    private function changePersonColumn($records, $action, $commit)
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->$action;
            $newValue = $person->$action = 1;
            $record->status = 'success';

            if ($commit) {
                $changes = $person->getChangedValues();
                if ($this->saveModel($person, $record)) {
                    $this->log('person-update', 'bulk update', $changes, $person->id);
                } else {
                    continue;
                }
            }
            $record->changes = [ $oldValue, 1 ];
        }
    }

    private function processBmid($records, $action, $commit)
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

            switch ($action) {
                case 'showers':
                    $showers = strtolower(trim($data[0]));
                    $oldValue = $bmid->showers;
                    $newValue = $bmid->showers = ($showers[0] == 'y' || $showers[0] == 1);
                    break;

                case 'meals':
                    $meals = trim($data[0]);
                    if ($meals[0] == "+") {
                        $meals = substr($meals, 1, strlen($meals)-1);
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
                    throw new \InvalidArgumentException('Unknown action');
                    break;
            }

            $record->status = 'success';
            if ($commit) {
                $exists = $bmid->exists;
                if ($exists) {
                    $changes = $bmid->getChangedValues();
                }
                $this->saveModel($bmid, $record);
                if ($record->status == 'success') {
                    if ($exists) {
                        $changes['id'] = $bmid->id;
                        $this->log('bmid-update', 'bulk update', $changes, $bmid->person_id);
                    } else {
                        $this->log('bmid-create', 'bulk update', $bmid->getAttributes(), $bmid->person_id);
                    }
                }
            }

            $record->changes = [ $oldValue, $newValue ];
        }
    }

    private function processTickets($records, $action, $commit)
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
                    $type = "staff_credential";
                    if (count($data) >= 2) {
                        $accessDate = trim($data[1]);
                    }
                    break;

                case 'RPT':
                    $type = 'reduced_price_ticket';
                    break;

                case 'GIFT':
                    $type = 'gift_ticket';
                    break;

                case 'VP':
                    $type = 'vehicle_pass';
                    break;

                case 'WAP':
                    $type = 'work_access_pass';
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
                } catch (\Exception $e) {
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
                    'person_id'   => $person->id,
                    'type'        => $type,
                    'source_year' => $sourceYear,
                    'expiry_date' => $expiryYear,
                    'comments'    => "$uploadDate {$this->user->callsign}: bulk uploaded",
                    'status'      => 'qualified',
                ]
            );

            if ($accessDate != null) {
                $ad->access_date = $accessDateCleaned;
            }

            $record->status = 'success';
            if ($commit) {
                $this->saveModel($ad, $record);
            }
        }
    }

    private function processWAPs($records, $action, $commit)
    {
        $year = current_year();
        $low = 5;
        $high = 26;
        $wapDate = config('TAS_WAPDateRange');
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
                $record->detail = 'missing acesss type';
            }

            $accessDate = trim($data[0]);

            $anytime = false;
            if (strtolower($accessDate) == "any") {
                $anytime = true;
            } else {
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (\Exception $e) {
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
            } elseif ($wap->status == 'submitted') {
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
                $oldValue = (string) $wap->access_date;
                if ($commit) {
                    AccessDocument::updateWAPsForPerson($person->id, $accessDate, $accessAnyTime);
                }
                $record->status = 'success';
            }
        }
    }

    private function processEventRadio($records, $action, $commit)
    {
        $year = current_year();

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            if (empty($record->data)) {
                $maxRadios = 1;
            } else {
                $maxRadios = (int) $record->data[0];
            }

            $radio = RadioEligible::firstOrNewForPersonYear($person->id, $year);
            $oldValue = $radio->max_radios;
            $newValue = $radio->max_radios = $maxRadios;

            $record->status = 'success';
            $record->changes = [ $oldValue, $newValue ];

            if ($commit) {
                $this->saveModel($radio, $record);
            }

        }
    }

    private function saveModel($model, $record)
    {
        try {
            $model->saveWithoutValidation();
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            $record->status = 'failed';
            $record->details = 'SQL Failure '.$e->getMessage();
            return false;
        }
    }
}
