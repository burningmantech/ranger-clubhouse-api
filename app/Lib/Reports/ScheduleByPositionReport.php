<?php

namespace App\Lib\Reports;

use App\Models\PositionLineup;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

class ScheduleByPositionReport
{
    /**
     * Report on all scheduled sign up by position for a given year
     *
     * @param int $year
     * @return array
     */

    public static function execute(int $year): array
    {
        $rows = Slot::select('slot.*')
            ->join('position', 'position.id', 'slot.position_id')
            ->where('begins_year', $year)
            ->with([
                'position:id,title,active',
                'sign_ups',
                'parent_signup_slot',
                'parent_signup_slot.position:id,title',
                'parent_signup_slot.sign_ups',
                'child_signup_slot',
                'child_signup_slot.position:id,title',
                'child_signup_slot.sign_ups',
            ])
            ->orderBy('position.title')
            ->orderBy('slot.begins')
            ->get()
            ->groupBy('position_id');

        $positionLineups = [];
        if ($rows->isNotEmpty()) {
            foreach ($rows->keys() as $id) {
                $positionLineups[$id] = PositionLineup::retrieveAssociatedPositions($id);
            }
        }

        $people = [];

        $positions = $rows->map(function ($p) use (&$people, $positionLineups) {
            $slot = $p[0];
            $position = $slot->position;

            return [
                'id' => $position->id,
                'title' => $position->title,
                'active' => $position->active,
                'slots' => $p->map(function ($slot) use (&$people, $positionLineups) {
                    $result = [
                        'id' => $slot->id,
                        'begins' => (string)$slot->begins,
                        'ends' => (string)$slot->ends,
                        'duration' => $slot->duration,
                        'tz' => $slot->timezone,
                        'tz_abbr' => $slot->timezone_abbr,
                        'active' => $slot->active,
                        'description' => (string)$slot->description,
                        'max' => $slot->max,
                        'sign_ups' => self::buildSignUps($slot->sign_ups, $people),
                    ];
                    if ($slot->parent_signup_slot) {
                        $result['parent'] = [
                            'id' => $slot->parent_signup_slot->id,
                            'position_id' => $slot->parent_signup_slot->position->id,
                            'position_title' => $slot->parent_signup_slot->position->title,
                            'sign_ups' => self::buildSignUps($slot->parent_signup_slot->sign_ups, $people),
                        ];
                    } else if ($slot->child_signup_slot) {
                        $result['child'] = [
                            'id' => $slot->child_signup_slot->id,
                            'position_id' => $slot->child_signup_slot->position->id,
                            'position_title' => $slot->child_signup_slot->position->title,
                            'sign_ups' => self::buildSignUps($slot->child_signup_slot->sign_ups, $people),
                        ];
                    }

                    $assocSignUps = [];
                    $lineups = $positionLineups[$slot->position_id] ?? [];
                    foreach ($lineups as $position) {
                        $slotId = DB::table('slot')
                            ->whereBetween('begins', [$slot->begins->clone()->subHour(), $slot->begins->clone()->addHour()])
                            ->where('position_id', $position->id)
                            ->where('slot.active', true)
                            ->value('id');

                        if ($slotId) {
                            $signups = DB::table('person_slot')
                                ->select('person.id', 'person.callsign', 'person.status')
                                ->join('person', 'person.id', 'person_slot.person_id')
                                ->where('person_slot.slot_id', $slotId)
                                ->orderBy('person.callsign')
                                ->get();
                        } else {
                            $signups = null;
                        }

                        $assocSignUps[] = [
                            'slot_id' => $slotId,
                            'position_id' => $position->id,
                            'position_title' => $position->title,
                            'people' => $signups ? self::buildSignUps($signups, $people) : null,
                        ];
                    }

                    if (!empty($assocSignUps)) {
                        $result['associated'] = $assocSignUps;
                    }

                    return $result;
                })->values()->toArray()
            ];
        })->values()->toArray();

        return [
            'positions' => $positions,
            'people' => $people
        ];
    }

    public static function buildSignUps($signUps, &$people): array
    {
        return $signUps->map(function ($person) use (&$people) {
            $personId = $person->id;
            $people[$personId] ??= [
                'id' => $personId,
                'callsign' => $person->callsign,
                'status' => $person->status,
            ];
            return $personId;
        })->toArray();
    }
}