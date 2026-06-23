<?php

namespace App\Lib\Reports;

use App\Models\PersonMentor;
use App\Models\Position;
use App\Models\Slot;
use App\Models\Timesheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MentorShiftReport
{
    const int HOURS_PRIOR = 1;
    const int HOURS_AFTER = 3;

    const array MENTOR_TYPES = [
        Position::MENTOR,
        Position::MENTOR_MITTEN,
    ];

    const array MENTOR_OPERATIONS = [
        Position::MENTOR_LEAD,
        Position::MENTOR_SHORT,
        Position::MENTOR_ALPHA_HOST,
        Position::MENTOR_KHAKI,
        Position::MENTOR_RADIO_TRAINER,
        Position::MENTOR_WHEEL_OF_MANY_WAYS,
        Position::MENTOR_APPRENTICE,
    ];

    const array MENTOR_CHEETAH_PRIDE = [
        Position::MENTOR_CHEETAH,
        Position::MENTOR_CHEETAH_CUB,
    ];

    const array MENTOR_ALL_POSITIONS = [
        POSITION::ALPHA,
        ...self::MENTOR_TYPES,
        ...self::MENTOR_CHEETAH_PRIDE,
        ...self::MENTOR_OPERATIONS,
    ];

    const array GROUPS = [
        [[Position::ALPHA], 'Alphas'],
        [self::MENTOR_TYPES, 'Mentors & Mittens'],
        [self::MENTOR_CHEETAH_PRIDE, 'Cheetahs & Cheetah Cubs'],
        [self::MENTOR_OPERATIONS, 'Mentor Operations / Support'],
    ];

    public static function execute(?int $alphaSlotId, bool $reportOnDuty = false): array
    {
        if ($reportOnDuty) {
            return self::executeForOnDuty();
        }

        $slot = Slot::find($alphaSlotId);
        if (!$slot) {
            throw ValidationException::withMessages([
                'slot_id' => ['Slot not found']
            ]);
        }

        if ($slot->position_id != Position::ALPHA) {
            throw ValidationException::withMessages([
                'slot_id' => ["The slot is not the  Alpha position"]
            ]);
        }

        if (!$slot->active) {
            throw ValidationException::withMessages([
                'slot_id' => ["The slot is not active"]
            ]);
        }

        $slotsByPosition = Slot::where('begins_year', $slot->begins_year)
            ->whereBetween('begins', [
                $slot->begins->clone()->subHours(self::HOURS_PRIOR),
                $slot->begins->clone()->addHours(self::HOURS_AFTER)
            ])->whereIn('position_id', self::MENTOR_ALL_POSITIONS)
            ->where('slot.active', true)
            ->with(['position', 'sign_ups:person.id,callsign,years_as_ranger'])
            ->get()
            ->groupBy('position.id')
            ->all();

        $results = [
            'slot' => [
                'id' => $slot->id,
                'description' => $slot->description,
                'begins' => (string)$slot->begins,
                'ends' => (string)$slot->ends,
                'duration' => $slot->duration
            ],
            'groups' => [],
        ];

        if (empty($slotsByPosition)) {
            return $results;
        }

        foreach (self::GROUPS as [$positionIds, $title]) {
            $results['groups'][] = self::buildGroup($slotsByPosition, $positionIds, $title);
        }

        return $results;
    }

    public static function buildGroup(array $slotsByPosition, array $positionIds, string $title): array
    {
        $positions = [];
        foreach ($positionIds as $positionId) {
            if (!in_array($positionId, $positionIds)) {
                continue;
            }

            $slots = $slotsByPosition[$positionId] ?? null;
            if (!$slots) {
                $position = Position::find($positionId);
                $positions[] = [
                    'id' => $position->id,
                    'title' => $position->title,
                    'slots' => [],
                ];
                continue;
            }

            $positionSlots = [];
            foreach ($slots as $slot) {
                $people = [];

                $priorBonks = ($positionId == Position::ALPHA && $slot->sign_ups->isNotEmpty())
                    ? self::fetchBonks($slot->sign_ups->pluck('id'))
                    : collect();
                foreach ($slot->sign_ups as $person) {
                    $result = [
                        'id' => $person->id,
                        'callsign' => $person->callsign,
                    ];

                    if ($positionId == Position::ALPHA) {
                        $result['bonks'] = self::bonksFor($priorBonks, $person->id);
                    } else {
                        $result['years_as_ranger'] = $person->years_as_ranger;
                    }

                    $people[] = $result;
                }

                $positionSlots[] = [
                    'id' => $slot->id,
                    'description' => $slot->description,
                    'begins' => (string)$slot->begins,
                    'ends' => (string)$slot->ends,
                    'duration' => $slot->duration,
                    'people' => $people,
                ];
            }

            $position = $slots[0]->position;
            $positions[] = [
                'id' => $position->id,
                'title' => $position->title,
                'slots' => $positionSlots,
            ];
        }

        return [
            'title' => $title,
            'positions' => $positions
        ];
    }

    public static function executeForOnDuty(): array
    {
        $peopleByPosition = Timesheet::whereIn('position_id', self::MENTOR_ALL_POSITIONS)
            ->whereYear('on_duty', current_year())
            ->whereNull('off_duty')
            ->with(['position:id,title', 'person:id,callsign,years_as_ranger'])
            ->get()
            ->sortBy('person.callsign')
            ->groupBy('position_id');

        $results = [
            'groups' => [],
            'now' => (string)now()
        ];

        if ($peopleByPosition->isEmpty()) {
            return $results;
        }

        foreach (self::GROUPS as [$positionIds, $title]) {
            $results['groups'][] = self::buildOnDutyGroup($peopleByPosition, $positionIds, $title);
        }

        return $results;
    }

    public static function buildOnDutyGroup(Collection $onDutyByPosition, array $positionIds, string $title): array
    {
        $grouped = $onDutyByPosition->filter(function ($group, $positionId) use ($positionIds) {
            return in_array($positionId, $positionIds);
        })->values();


        $positions = [];
        foreach ($grouped as $onDuty) {
            $position = $onDuty[0]->position;
            $positionId = $position->id;
            $priorBonks = $positionId == Position::ALPHA
                ? self::fetchBonks($onDuty->pluck('person_id'))
                : collect();
            $people = [];
            foreach ($onDuty as $entry) {
                $person = $entry->person;
                $result = [
                    'id' => $person->id,
                    'callsign' => $person->callsign,
                    'on_duty' => (string)$entry->on_duty,
                    'duration' => $entry->duration,
                ];

                if ($positionId == Position::ALPHA) {
                    $result['bonks'] = self::bonksFor($priorBonks, $person->id);
                } else {
                    $result['years_as_ranger'] = $person->years_as_ranger;
                }

                $people[] = $result;
            }
            $positions[] = [
                'id' => $position->id,
                'title' => $position->title,
                'people' => $people,
            ];
        }

        return [
            'title' => $title,
            'positions' => $positions
        ];
    }

    private static function fetchBonks(Collection $personIds): Collection
    {
        return DB::table('person_mentor')
            ->select('person_id', 'mentor_year')
            ->whereIn('person_id', $personIds)
            ->whereIn('status', [PersonMentor::BONK, PersonMentor::SELF_BONK])
            ->groupBy(['person_id', 'mentor_year'])
            ->get()
            ->groupBy('person_id');
    }

    /**
     * @return int[] the person's bonk years, sorted ascending and re-indexed so
     * the result serializes as a JSON array rather than a keyed object.
     */
    private static function bonksFor(Collection $priorBonks, int $personId): array
    {
        return ($bonks = $priorBonks->get($personId))
            ? $bonks->pluck('mentor_year')->sort()->values()->all()
            : [];
    }
}
