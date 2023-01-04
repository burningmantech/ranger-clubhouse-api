<?php

namespace App\Lib;

use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Facades\DB;

class TimesheetSlotAssocRepair
{
    const SHIFT_START_BEFORE_MINS = 45;
    const SHIFT_START_AFTER_MINS = 60;

    public static function execute(int $year): array
    {
        $entries = [];
        $positionsById = Position::all()->keyBy('id');
        Timesheet::whereYear('on_duty', $year)
            ->orderBy('id')
            ->with('person:id,callsign,status', 'slot:id,description,begins,ends,position_id')
            ->chunk(1000, function ($rows) use (&$entries, $positionsById) {
                foreach ($rows as $row) {
                    $newSlot = null;
                    $start = $row->on_duty->clone()->subMinutes(self::SHIFT_START_BEFORE_MINS);
                    $end = $row->on_duty->clone()->addMinutes(self::SHIFT_START_AFTER_MINS);

                    if ($row->slot) {
                        if ($row->position_id == $row->slot->position_id
                            && $row->on_duty->gt($start) && $row->on_duty->lt($end)) {
                            // Still good..
                            continue;
                        }
                    }

                    $signUp = DB::table('slot')
                        ->select('slot.*')
                        ->join('person_slot', 'slot.id', 'person_slot.slot_id')
                        ->where('slot.position_id', $row->position_id)
                        ->whereBetween('begins', [$start, $end])
                        ->where('person_slot.person_id', $row->person_id)
                        ->first();

                    if ($signUp) {
                        $newSlot = $signUp;
                    } else {
                        $newSlot = DB::table('slot')
                            ->whereBetween('begins', [$start, $end])
                            ->where('position_id', $row->position_id)
                            ->first();
                    }

                    $slotId = $newSlot?->id;
                    if ($row->slot_id == $slotId) {
                        continue; // All good.
                    }

                    $oldSlot = $row->slot;
                    $row->slot_id = $slotId;
                    $row->auditReason = 'slot assoc. repair';
                    $row->saveWithoutValidation();
                    $position = $positionsById[$row->position_id];
                    $entry = [
                        'id' => $row->id,
                        'on_duty' => (string)$row->on_duty,
                        'position' => [
                            'id' => $row->position_id,
                            'title' => $position->title ?? "Position #{$row->position_id}",
                        ],
                        'person' => [
                            'id' => $row->person_id,
                            'callsign' => $row->person->callsign ?? "Person #{$row->person_id}",
                            'status' => $row->person->status ?? "unknown",
                        ]
                    ];
                    if ($oldSlot) {
                        $position = $positionsById[$oldSlot->position_id];
                        $entry['old_slot'] = [
                            'id' => $oldSlot->id,
                            'begins' => (string)$oldSlot->begins,
                            'description' => (string)$oldSlot->description,
                            'position' => [
                                'id' => $oldSlot->position_id,
                                'title' => $position->title ?? "Position #{$row->position_id}",
                            ]
                        ];
                    }
                    if ($newSlot) {
                        $entry['new_slot'] = [
                            'id' => $newSlot->id,
                            'begins' => (string)$newSlot->begins,
                            'description' => (string)$newSlot->description,
                        ];
                    }

                    $entries[] = $entry;
                }
            });

        usort($entries, fn($a, $b) => strcasecmp($a['person']['callsign'], $b['person']['callsign']));
        return $entries;
    }

}