<?php

namespace App\Lib\Reports;

use App\Models\PersonPosition;
use App\Models\Position;
use App\Models\TraineeNote;
use App\Models\TraineeStatus;

class TrainingNotesReport
{
    public static function execute(int $positionId, int $year): array
    {
        $art = Position::ART_GRADUATE_TO_POSITIONS[$positionId] ?? null;

        $rows = TraineeStatus::select('trainee_status.*')
            ->with(['slot', 'person:id,callsign,status'])
            ->join('slot', 'trainee_status.slot_id', 'slot.id')
            ->join('person_slot', function ($j) {
                $j->on('slot.id', 'person_slot.slot_id')
                    ->whereColumn('trainee_status.person_id', 'person_slot.person_id');
            })->where('slot.begins_year', $year)
            ->where('slot.active', 1)
            ->where('slot.position_id', $positionId)
            ->where('trainee_status.passed', true)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $personIds = $rows->pluck('person_id')->unique();
        $slotIds = $rows->pluck('slot_id')->unique();

        $notesByPerson = TraineeNote::whereIntegerInRaw('person_id', $personIds)
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->where('is_log', false)
            ->orderBy('person_id')
            ->orderBy('slot_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('person_id');

        $people = [];

        if ($art) {
            if ($art['veteran'] ?? null) {
                $graduates = PersonPosition::whereIntegerInRaw('person_id', $personIds)
                    ->where('position_id', $art['veteran'])
                    ->get()
                    ->groupBy('person_id');
            } else {
                $graduates = collect();
            }

            if ($art['has_mentees'] ?? null) {
                $mentees = PersonPosition::whereIntegerInRaw('person_id', $personIds)
                    ->where('position_id', $art['positions'])
                    ->get()
                    ->groupBy('person_id');
            } else {
                $mentees = collect();
            }
        } else {
            $graduates = collect();
            $mentees = collect();
        }

        foreach ($rows as $row) {
            $notes = $notesByPerson->get($row->person_id);

            if (!isset($people[$row->person_id])) {
                if ($graduates->has($row->person_id)) {
                    $standing = 'veteran';
                } else if ($mentees->has($row->person_id)) {
                    $standing = 'mentee';
                } else {
                    $standing = 'prospective';
                }

                $people[$row->person_id] = [
                    'id' => $row->person_id,
                    'callsign' => $row->person->callsign,
                    'status' => $row->person->status,
                    'standing' => $standing,
                    'slots' => []
                ];
            }

            $person = &$people[$row->person_id];
            if ($notes) {
                foreach ($notes as $note) {
                    $person['slots'][$note->slot_id] ??= [
                        'id' => $row->slot_id,
                        'description' => $row->slot->description,
                        'begins' => (string)$row->slot->begins,
                        'timezone_abbr' => $row->slot->timezone_abbr,
                        'rank' => $row->rank,
                        'notes' => []
                    ];

                    $person['slots'][$note->slot_id]['notes'][] = $note->note;
                }
            } else {
                $person['slots'][$row->slot_id] ??= [
                    'id' => $row->slot_id,
                    'description' => $row->slot->description,
                    'begins' => (string)$row->slot->begins,
                    'timezone_abbr' => $row->slot->timezone_abbr,
                    'rank' => $row->rank,
                    'notes' => []
                ];
            }
        }
        unset($person);

        $people = array_values($people);
        usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        foreach ($people as &$person) {
            $person['slots'] = array_values($person['slots']);
        }

        return $people;
    }
}