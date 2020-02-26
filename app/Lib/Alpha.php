<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonIntake;
use App\Models\PersonIntakeNote;
use App\Models\PersonMentor;
use App\Models\PersonPhoto;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use App\Models\Training;

use Illuminate\Support\Facades\DB;

class Alpha
{
    public static function retrieveMentors()
    {
        $year = current_year();
        $positionId = Position::MENTOR;

        $rows = DB::table('person')
            ->select(
                'person.id',
                'person.callsign',
                DB::raw("EXISTS (SELECT 1 FROM timesheet WHERE timesheet.person_id=person.id AND position_id=$positionId AND YEAR(on_duty)=$year AND off_duty IS NULL) as working")
            )
            ->join('person_position', 'person_position.person_id', '=', 'person.id')
            ->where('person_position.position_id', Position::MENTOR)
            ->orderBy('callsign')
            ->get();
        return $rows;
    }

    /**
     * Retrieve Potential Alphas
     *
     * @param bool $noBonks true if Bonked status are to be included
     * @return array
     */

    public static function retrievePotentials($noBonks = false)
    {
        $year = current_year();

        // Find potential alphas who signed up for training this year.
        $statuses = $noBonks ? [Person::ALPHA, Person::PROSPECTIVE] : [Person::ALPHA, Person::PROSPECTIVE, Person::BONKED];
        $sql = Person::whereIn('status', $statuses)
            ->where('user_authorized', true)
            ->whereRaw("EXISTS
                (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id
                    AND slot.position_id=? AND YEAR(slot.begins)=?
                WHERE person_slot.person_id=person.id LIMIT 1)", [Position::TRAINING, $year])
            ->orderBy('callsign');

        $rows = $sql->get();

        return self::buildAlphaInformation($rows, $year);
    }

    public static function buildAlphaInformation($people, $year)
    {
        if ($people->isEmpty()) {
            return [];
        }

        list ($intakeHistory, $intakeNotes, $trainings) = self::retrieveIntakeHistory($people->pluck('id')->toArray());

        // Find out the signed up shifts
        $alphaSlots = DB::table('person_slot')
            ->select(
                'slot.id as slot_id',
                'slot.description',
                'slot.begins',
                'person_slot.person_id'
            )->join('slot', function ($j) use ($year) {
                $j->on('slot.id', 'person_slot.slot_id');
                $j->whereYear('slot.begins', $year);
                $j->where('slot.position_id', Position::ALPHA);
            })
            ->whereIn('person_slot.person_id', $people->pluck('id')->toArray())
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('person_id');

        $potentials = [];
        foreach ($people as $row) {
            $person = self::buildPerson($row, $year, $intakeHistory, $intakeNotes, $trainings);
            $slot = $alphaSlots[$row->id] ?? null;
            if ($slot) {
                // Only grab the first slot
                $slot = $slot[0];
                $person->alpha_slot = [
                    'slot_id' => $slot->slot_id,
                    'begins' => $slot->begins,
                    'description' => $slot->description,
                ];
            }
            $potentials[] = $person;
        }

        return $potentials;
    }

    public static function retrieveAllAlphas()
    {
        $rows = PersonPosition::where('position_id', Position::ALPHA)
            ->with('person')
            ->get()
            ->filter(function ($r) {
                return $r->person != null;
            })
            ->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)
            ->pluck('person')
            ->values();

        return self::buildAlphaInformation($rows, current_year());
    }

    public static function retrieveAlphaScheduleForYear($year)
    {
        // Find the Alpha slots
        $slots = Slot::whereYear('begins', $year)
            ->where('position_id', Position::ALPHA)
            ->get();

        // Next, find the Alpha sign ups
        $rows = PersonSlot::whereIn('slot_id', $slots->pluck('id')->toArray())
            ->with('person')
            ->get()
            ->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $slotInfo = $slots->map(function ($slot) {
            return (object)[
                'id' => $slot->id,
                'begins' => (string)$slot->begins,
                'description' => $slot->description,
                'people' => []
            ];
        });


        $slotsById = $slotInfo->keyBy('id');

        list ($intakeHistory, $intakeNotes, $trainings) = self::retrieveIntakeHistory($rows->pluck('person_id'));

        foreach ($rows as $row) {
            $slotsById[$row->slot_id]->people[] = self::buildPerson($row->person, $year, $intakeHistory, $intakeNotes, $trainings);
        }

        return $slotInfo;
    }

    public static function buildPerson($person, $year, $intakeHistory, $intakeNotes, $trainings)
    {
        $personId = $person->id;
        $potential = (object)[
            'id' => $personId,
            'callsign' => $person->callsign,
            'callsign_approved' => $person->callsign_approved,
            'fkas' => $person->formerlyKnownAsArray(true),
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'email' => $person->email,
            'status' => $person->status,
            'gender' => $person->gender,
            'mentor_history' => PersonMentor::retrieveMentorHistory($person->id),
            'photo_approved' => PersonPhoto::retrieveStatus($person) == PersonPhoto::APPROVED,
            'has_note_on_file' => $person->has_note_on_file,
            'city' => $person->city,
            'state' => $person->state,
            'country' => $person->country,
            'longsleeveshirt_size_style' => $person->longsleeveshirt_size_style,
            'teeshirt_size_style' => $person->teeshirt_size_style,
            'trained' => false,
            'trainings' => $trainings[$personId] ?? [],
            'on_alpha_shift' => Timesheet::isPersonSignIn($personId, Position::ALPHA)
        ];

        $rrnRanks = [];
        $vcRanks = [];

        $teamHistory = $intakeHistory[$personId] ?? null;
        if (!empty($teamHistory)) {
            foreach ($teamHistory as $r) {
                if ($r->year == $year && $r->black_flag) {
                    $potential->black_flag = true;
                }

                if ($r->rrn_rank > 0 && $r->rrn_rank != Intake::AVERAGE) {
                    $rrnRanks[] = ['year' => $r->year, 'rank' => $r->rrn_rank];
                }

                if ($r->vc_rank > 0 && $r->vc_rank != Intake::AVERAGE) {
                    $vcRanks[$r->year] = ['year' => $r->year, 'rank' => $r->vc_rank];
                }
            }
        }

        $potential->rrn_ranks = $rrnRanks;
        $potential->vc_ranks = $vcRanks;
        $potential->mentor_team = Intake::buildIntakeTeam('mentor', $teamHistory, $intakeNotes[$personId] ?? null, $haveFlag);

        if (!empty($teamHistory)) {
            foreach ($teamHistory as $history) {
                if ($history->mentor_rank >= Intake::BELOW_AVERAGE) {
                    $potential->have_mentor_flags = true;
                }

                if ($history->year == $year && $history->black_flag) {
                    $potential->black_flag = true;
                }
            }
        }

        foreach ($potential->trainings as $training) {
            if ($training->slot_year == $year && $training->training_passed) {
                $potential->trained = true;
            }
        }

        if ($potential->status == Person::PROSPECTIVE && $potential->callsign_approved && $potential->photo_approved) {
            $potential->alpha_status_eligible = true;
        } else if ($potential->status == Person::ALPHA) {
            $potential->alpha_status_eligible = true;
        }

        if ($potential->trained && $potential->callsign_approved && $potential->photo_approved) {
            $potential->alpha_position_eligible = true;
        }

        return $potential;
    }

    public static function retrieveVerdicts()
    {
        $year = current_year();

        $people = DB::table('person')
            ->select('person.id', 'callsign', 'person.status', 'first_name', 'last_name',
                DB::raw("(SELECT person_mentor.status FROM person_mentor WHERE person_mentor.person_id=person.id AND mentor_year=$year GROUP BY person_mentor.status LIMIT 1) as mentor_status")
            )
            ->where('status', Person::ALPHA)
            ->orderBy('person.callsign')
            ->get();
        return $people->filter(function ($p) {
            return $p->mentor_status != 'pending';
        })->values();
    }

    public static function retrieveIntakeHistory($pnvIds)
    {
        $year = current_year();

        $intakeHistory = PersonIntake::whereIn('person_id', $pnvIds)
            ->orderBy('person_id')
            ->orderBy('year')
            ->get()
            ->groupBy('person_id');

        $intakeNotes = PersonIntakeNote::retrieveHistoryForPersonIds($pnvIds, $year);

        $trainings = Training::retrieveTrainingHistoryForIds($pnvIds, Position::TRAINING, $year);

        return [$intakeHistory, $intakeNotes, $trainings];
    }
}
