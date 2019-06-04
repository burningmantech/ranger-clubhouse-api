<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\PersonMentor;
use App\Models\PersonPosition;
use App\Models\PersonSlot;
use App\Models\Photo;
use App\Models\Position;
use App\Models\Slot;
use App\Models\TraineeStatus;
use App\Models\Training;

use Illuminate\Support\Facades\DB;

class Alpha
{
    public static function retrieveMentors()
    {
        $year = date('Y');
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

    public static function retrievePotentials($noBonks = false, $excludePhotos=false)
    {
        $year = date('Y');

        // Find potential alphas who signed up for training this year.
        $statuses = $noBonks ? [ 'alpha', 'prospective' ] : [ 'alpha', 'prospective', 'bonked' ];
        $sql = Person::whereIn('status', $statuses)
            ->where('user_authorized', true)
            ->whereRaw("EXISTS
                (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id
                    AND slot.position_id=? AND YEAR(slot.begins)=?
                WHERE person_slot.person_id=person.id LIMIT 1)", [ Position::DIRT_TRAINING, $year ])
            ->orderBy('callsign');

        $rows = $sql->get();

        return self::buildAlphaInformation($rows, date('Y'), $excludePhotos);
    }

    public static function buildAlphaInformation($people, $year, $excludePhotos = false)
    {
        if ($people->isEmpty()) {
            return [];
        }

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

        $potentials = $people->map(function ($row) use ($year, $alphaSlots, $excludePhotos) {
            $person = self::buildPerson($row, $year, $excludePhotos);
            $slot = $alphaSlots[$row->id] ?? null;
            if ($slot) {
                // Only grab the first slot
                $slot = $slot[0];

                $person->alpha_slot = [
                    'slot_id'   => $slot->slot_id,
                    'begins'    => $slot->begins,
                    'description' => $slot->description,
                ];
            }
            return $person;
        });

        return $potentials;
    }

    public static function findAllAlphas()
    {
        $rows = PersonPosition::where('position_id', Position::ALPHA)
                ->with('person')
                ->get()
                ->filter(function ($r) {
                    return $r->person != null;
                })
                ->sortBy('person.callsign')
                ->pluck('person')
                ->values();

        return self::buildAlphaInformation($rows, date('Y'));
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
                ->sortBy('person.callsign')
                ->values();

        $slotInfo = $slots->map(function($slot) {
            return (object) [
                'id'          => $slot->id,
                'begins'      => (string) $slot->begins,
                'description' => $slot->description,
                'people'      => []
            ];
        });


        $slotsById = $slotInfo->keyBy('id');

        foreach ($rows as $row) {
            $slotsById[$row->slot_id]->people[] = self::buildPerson($row->person, $year, true);
        }

        return $slotInfo;
    }

    public static function buildPerson($row, $year, $excludePhotos=false) {
        $fka = $row->formerly_known_as;
        if (empty($fka)) {
            $fka = null;
        } else {
            $fka = preg_split('/\s*,\s*/', $fka);
        }
        $person = (object) [
            'id'                => $row->id,
            'callsign'          => $row->callsign,
            'callsign_approved' => $row->callsign_approved,
            'fka'               => $fka,
            'first_name'        => $row->first_name,
            'last_name'         => $row->last_name,
            'email'             => $row->email,
            'status'            => $row->status,
            'gender'            => Person::summarizeGender($row->gender),
            'mentors_flag'      => $row->mentors_flag,
            'mentors_flag_note' => $row->mentors_flag_note,
            'mentors_notes'     => $row->mentors_notes,
            'mentor_history'    => PersonMentor::retrieveMentorHistory($row->id),
            'has_note_on_file'  => $row->has_note_on_file,
            'create_date'       => (string) $row->create_date,
            'city'              => $row->city,
            'state'             => $row->state,
            'country'           => $row->country,
            'longsleeveshirt_size_style' => $row->longsleeveshirt_size_style,
            'teeshirt_size_style' => $row->teeshirt_size_style,
            'trained'           => false,
            'trainings'         => Training::retrieveDirtTrainingsForPersonYear($row->id, $year),
        ];

        $result = DB::select('SELECT 1 AS on_duty FROM timesheet WHERE person_id=? AND YEAR(on_duty)=? AND position_id=? AND off_duty IS NULL LIMIT 1',
                    [ $row->id, $year, Position::ALPHA] );
        $person->on_alpha_shift = (isset($result[0]) && $result[0]->on_duty);

        if (!$excludePhotos) {
            $photo = Photo::retrieveInfo($row);
            $person->photo_approved = ($photo['photo_status'] == 'approved');
            $person->photo_status = $photo['photo_status'];
            if ($person->photo_approved) {
                $person->photo_url = $photo['photo_url'];
            }
        }

        // Figure out if the person passed (any) training
        foreach ($person->trainings as $t) {
            if ($t->passed) {
                $person->trained = true;
                break;
            }
        }

        return $person;
    }

    public static function retrieveVerdicts()
    {
        $year = date('Y');

        $people = DB::table('person')
                ->select('person.id', 'callsign', 'person.status', 'first_name', 'last_name',
                    DB::raw("(SELECT person_mentor.status FROM person_mentor WHERE person_mentor.person_id=person.id AND mentor_year=$year GROUP BY person_mentor.status LIMIT 1) as mentor_status")
                )
                ->where('status', 'alpha')
                ->orderBy('person.callsign')
                ->get();
        return $people->filter(function ($p) { return $p->mentor_status != 'pending'; });
    }
}
