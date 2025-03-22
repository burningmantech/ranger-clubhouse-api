<?php

namespace App\Lib\Reports;

use App\Models\ActionLog;
use App\Models\Person;
use App\Models\PersonOnlineCourse;
use App\Models\PersonPhoto;
use App\Models\PersonSlot;
use App\Models\PersonStatus;
use App\Models\Position;
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

        // need to handle the situation where multiple accidental conversions happened, hence the groupBy().
        $pnvStatus = PersonStatus::select('person_id', 'created_at')
            ->whereYear('created_at', $year)
            ->where('new_status', Person::PROSPECTIVE)
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        if ($pnvStatus->isEmpty()) {
            // Too soon?
            return $spigot->dates;
        }

        foreach ($pnvStatus as $personId => $rows) {
            $spigot->setSpigotDate('imported', $rows[0]->created_at, $rows[0]->person);
        }

        $pnvIds = $pnvStatus->keys()->toArray();

        $loggedIn = ActionLog::select('person_id', 'created_at')
            ->whereIn('person_id', $pnvIds)
            ->whereYear('created_at', $year)
            ->where('event', 'auth-login')
            ->with('person:id,callsign')
            ->get()
            ->groupBy('person_id');

        foreach ($loggedIn as $personId => $logins) {
            $import = $pnvStatus->get($personId)->first()->created_at;
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
        if (!$person) {
            // Deleted account.
            return;
        }

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

        $this->dates[$day][$type][] = ['id' => $person->id, 'callsign' => $person->callsign];
    }
}