<?php

namespace App\Lib\Reports;

use App\Models\ActionLog;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonSlot;
use App\Models\PersonStatus;
use App\Models\Position;
use App\Models\ProspectiveApplication;
use App\Models\Slot;
use App\Models\TraineeStatus;
use Carbon\Carbon;

class SpigotFlowReport
{
    public array $dates = [];

    /**
     * Build up the counts for a given year and day.
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {
        $spigot = new SpigotFlowReport;

        $rawQueryData = [];

        // for years prior to 2025, SOR for pnv applications came was Salesforce, so both the import and created column
        // will be the same since we don’t know how many total applications were received. The applications were vetted
        // in SF, and only those approved were retrieve to create Clubhouse accounts with (aka imported). The actual
        // application was not stored in the Clubhouse.
        if ($year < 2025) {
            // need to handle the situation where multiple accidental conversions happened, hence the groupBy().
            $query = PersonStatus::select('person_id', 'created_at')
                ->whereYear('created_at', $year)
                ->where('new_status', Person::PROSPECTIVE)
                ->orderBy('created_at')
                ->get()
                ->groupBy('person_id');
            // just grab the first record from each group-by array
            foreach ($query as $personId => $rows) {
                $rawQueryData[] = $rows[0];
            }
        } else { // year >= 2025
          // For 2025 and beyond, the import column will reflect the prospective_application total record count for the
          // year. Not all applications will be turned into prospective accounts. The reasons an account may not be
          // created are: the application was pre-bonked (problematic behavior reported by the community, etc.), a
          // duplicate application was submitted, a returning Shiny Penny thought they had to submit a new
          // application, etc.
            $query = ProspectiveApplication::select('person_id', 'created_at', 'status')
                ->whereYear('created_at', $year)
                ->orderBy('created_at')
                ->get();

            // can't put into an associative array of person_id -> data because person_id can be null
            foreach ($query as $data) {
                $rawQueryData[] = $data;
            }
        }

        if (!$rawQueryData) {
            // Too soon?
            return $spigot->dates;
        }

        $pnvStatus = []; // associative array of personId -> data
        $pnvIds = []; // array of personIds

        if ($year < 2025) {
            foreach ($rawQueryData as $data) {
                $spigot->setSpigotDate('imported', $data->created_at, $data->person);
                $spigot->setSpigotDate('created', $data->created_at, $data->person);
                $pnvStatus[$data->person_id] = $data;
                $pnvIds[] = $data->person_id;
            }
        } else {
            // all records will have a created_at, which signifies their 'imported' date
            // however, sometimes personId will be null if the prospective application was not accepted,
            // so only a subset have a 'created' date
            foreach ($rawQueryData as $data) {
                $spigot->setSpigotDate('imported', $data->created_at, null); // not all imported have person-details
                if ($data->status == ProspectiveApplication::STATUS_CREATED) {
                    $spigot->setSpigotDate('created', $data->created_at, $data->person);
                    $pnvStatus[$data->person_id] = $data;
                    $pnvIds[] = $data->person_id;
                }
            }
        }

        $loggedIn = ActionLog::select('person_id', 'created_at')
            ->whereIn('person_id', $pnvIds)
            ->whereYear('created_at', $year)
            ->where('event', 'auth-login')
            ->with('person:id,callsign')
            ->get()
            ->groupBy('person_id');

        foreach ($loggedIn as $personId => $logins) {
            $import = $pnvStatus[$personId]->created_at;
            foreach ($logins as $login) {
                if ($login->created_at->gte($import)) {
                    $spigot->setSpigotDate('first_login', $login->created_at, $login->person);
                    break;
                }
            }
        }

        // need to handle the situation where multiple accidental conversions happened, hence the groupBy().
        $droppedStatus = PersonStatus::select('person_id', 'created_at', 'new_status')
            ->whereYear('created_at', $year)
            ->where('new_status', Person::PAST_PROSPECTIVE)
            ->whereIntegerInRaw('person_id', $pnvIds)
            ->orderBy('created_at')
            ->with('person:id,callsign')
            ->get()
            ->groupBy('person_id');

        foreach ($droppedStatus as $personId => $rows) {
            $spigot->setSpigotDate('dropped', $rows[0]->created_at, $rows[0]->person);
        }

        // Retrieve photo approval
        $photos = PersonPhoto::select('person_id', 'uploaded_at', 'reviewed_at')
            ->where('person_photo.status', PersonPhoto::APPROVED)
            ->join('person', 'person.person_photo_id', 'person_photo.id')
            ->whereIntegerInRaw('person_photo.person_id', $pnvIds)
            ->with('person:id,callsign')
            ->get();

        foreach ($photos as $photo) {
            // photos prior to 2020 will not have a review_at date because the information could not be
            // gotten out of Lambase.
            $date = $photo->reviewed_at ?? $photo->uploaded_at;
            $photoYear = $date?->year;
            $spigot->setSpigotDate('photo_approved', ($photoYear == $year ? $date : 'previous'), $photo->person);
        }

        $onlineCourse = PersonOnlineCourse::whereIntegerInRaw('person_id', $pnvIds)
            ->where('year', $year)
            ->where('position_id', Position::TRAINING)
            ->whereNotNull('completed_at')
            ->with('person:id,callsign')
            ->get();

        foreach ($onlineCourse as $poc) {
            $spigot->setSpigotDate('online_trained', $poc->completed_at, $poc->person);
        }

        // Grab the training slots
        $trainingSlotIds = Slot::select('id')
            ->where('position_id', Position::TRAINING)
            ->where('begins_year', $year)
            ->get()
            ->pluck('id')
            ->toArray();

        if (!empty($trainingSlotIds)) {
            $trainingSignups = PersonSlot::whereIntegerInRaw('person_id', $pnvIds)
                ->whereIntegerInRaw('slot_id', $trainingSlotIds)
                ->with('person:id,callsign')
                ->get()
                ->groupBy('person_id');

            foreach ($trainingSignups as $personId => $rows) {
                $spigot->setSpigotDate('training_signups', $rows[0]->created_at, $rows[0]->person);
            }

            // Grab the passing PNVs
            $trainingPass = TraineeStatus::select('trainee_status.*', 'slot.begins')
                ->join('slot', 'slot.id', 'trainee_status.slot_id')
                ->whereIntegerInRaw('person_id', $pnvIds)
                ->whereIntegerInRaw('slot_id', $trainingSlotIds)
                ->where('passed', true)
                ->with('person:id,callsign')
                ->get()
                ->groupBy('person_id');

            foreach ($trainingPass as $personId => $rows) {
                $spigot->setSpigotDate('training_passed', $rows[0]->begins, $rows[0]->person);
            }
        }

        // Grab the Alpha slots
        $alphaSlotIds = Slot::select('id')
            ->where('position_id', Position::ALPHA)
            ->where('begins_year', $year)
            ->get()
            ->pluck('id')
            ->toArray();

        if (!empty($alphaSlotIds)) {
            $alphaSignups = PersonSlot::whereIntegerInRaw('person_id', $pnvIds)
                ->whereIntegerInRaw('slot_id', $alphaSlotIds)
                ->with('person:id,callsign')
                ->get()
                ->groupBy('person_id');

            foreach ($alphaSignups as $personId => $rows) {
                $spigot->setSpigotDate('alpha_signups', $rows[0]->created_at, $rows[0]->person);
            }
        }

        $days = [];
        foreach ($spigot->dates as $day => $stats) {
            $stats['day'] = $day;
            $days[] = $stats;
        }

        foreach ($days as &$row) {
            foreach ($row as $key => &$people) {
                if ($key == 'day') {
                    continue;
                }
                usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
            }
            unset($people);
        }

        usort($days, fn($a, $b) => strcmp($a['day'], $b['day']));

        return $days;
    }

    public function setSpigotDate(string $type, $date, $person): void
    {
        if ($date != 'previous') {
            if (is_numeric($date)) {
                $date = new Carbon($date);
            } else if (is_string($date)) {
                $date = Carbon::parse($date);
            }

            $day = $date->format('Y-m-d');
        } else {
            $day = '0';
        }

        if (!isset($this->dates[$day][$type])) {
            $this->dates[$day][$type] = [];
        }

        if (!$person) { // Deleted account, or rejected application
            $this->dates[$day][$type][] = ['id' => '', 'callsign' => ''];
        } else {
            $this->dates[$day][$type][] = ['id' => $person->id, 'callsign' => $person->callsign];
        }
    }
}