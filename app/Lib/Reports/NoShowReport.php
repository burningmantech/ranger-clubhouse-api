<?php

namespace App\Lib\Reports;

use App\Models\Position;
use Illuminate\Support\Facades\DB;

class NoShowReport
{
    public static function execute(array $positionIds, int $year): array
    {
        $positions = Position::find($positionIds);

        $results = [];
        foreach ($positions as $position) {
            $results[] = [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'slots' => self::retrieveNoShowForPosition($position->id, $year)
            ];
        }

        return $results;
    }

    public static function retrieveNoShowForPosition($positionId, int $year): array
    {
        $slotGroups = DB::table('slot')
            ->select('slot.id as slot_id', 'slot.description', 'slot.begins', 'person.id', 'person.callsign')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->join('person', 'person.id', 'person_slot.person_id')
            ->where('slot.begins_year', $year)
            ->where('slot.begins', '<=', now())
            ->where('slot.position_id', $positionId)
            ->whereNotExists(function ($sql) use ($positionId, $year) {
                $sql->select(DB::raw(1))
                    ->from('timesheet')
                    ->whereColumn('timesheet.person_id', 'person_slot.person_id')
                    ->where('timesheet.position_id', $positionId)
                    ->whereYear('timesheet.on_duty', $year)
                    ->where(function ($w) {
                        $w->where(function ($q) {
                            $q->whereColumn('timesheet.on_duty', '<=', 'slot.begins');
                            $q->whereColumn('timesheet.off_duty', '>=', 'slot.ends');
                        })->orWhere(function ($q) {
                            // Shift happens within the period
                            $q->whereColumn('timesheet.on_duty', '<=', 'slot.begins');
                            $q->whereColumn('timesheet.off_duty', '>', 'slot.begins');
                        })->orWhere(function ($q) {
                            // Shift happens within the period
                            $q->whereColumn('timesheet.on_duty', '>=', 'slot.begins');
                            $q->whereColumn('timesheet.off_duty', '<=', 'slot.ends');
                        })->orWhere(function ($q) {
                            // Shift ends within the period
                            $q->whereColumn('timesheet.off_duty', '>=', 'slot.begins');
                            $q->whereColumn('timesheet.off_duty', '<=', 'slot.ends');
                        })->orWhere(function ($q) {
                            // Shift begins within the period
                            $q->whereColumn('timesheet.on_duty', '>=', 'slot.begins');
                            $q->whereColumn('timesheet.on_duty', '<=', 'slot.ends');
                        });
                    })
                    ->limit(1);
            })
            ->orderBy('slot.begins')
            ->orderBy('person.callsign')
            ->get()
            ->groupBy('slot_id');

        $slots = [];
        foreach ($slotGroups as $slotId => $group) {
            $slot = $group[0];
            $slots[] = [
                'id' => $slotId,
                'begins' => $slot->begins,
                'description' => $slot->description,
                'people' => $group->map(fn($p) => [
                    'id' => $p->id,
                    'callsign' => $p->callsign
                ])->toArray()
            ];
        }

        return $slots;
    }
}