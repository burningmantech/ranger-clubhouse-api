<?php

namespace App\Lib\Reports;

use App\Models\Position;
use App\Models\Timesheet;
use Illuminate\Support\Collection;

class ShiftCommandPhotoBoardReport
{
    const array SHIFT_COMMAND_PERSONNEL = [
        [
            'title' => 'For Questions',
            'positions' => [Position::ROC_STAR]
        ],
        [
            'title' => 'Khakis',
            'positions' => [
                Position::RSC_SHIFT_LEAD_PRE_EVENT,
                Position::RSC_SHIFT_LEAD,
                Position::RSCI,
            ]
        ],
        [
            'title' => 'Troubleshooters',
            'positions' => [
                Position::TROUBLESHOOTER,
                Position::TROUBLESHOOTER_LEAL,
                Position::TROUBLESHOOTER_LEAL_PRE_EVENT,
                Position::TROUBLESHOOTER_PRE_EVENT,
                Position::TROUBLESHOOTER_MENTOR,
                Position::TROUBLESHOOTER_TRAINER,
            ]
        ],
        [
            'title' => 'Operators',
            'positions' => [
                Position::OPERATOR,
                Position::OPERATOR_SMOOTH,
            ]
        ],
        [
            'title' => 'OODs',
            'positions' => [
                Position::OOD,
                Position::DEPUTY_OOD
            ]
        ]
    ];

    public static function execute(?string $period): array
    {
        if (!$period) {
            return self::executeForOnDuty();
        }

        return self::executeForPeriod($period);
    }

    public static function executeForOnDuty(): array
    {
        $positionsById = self::retrievePositions();

        $onDuty = Timesheet::whereIn('position_id', $positionsById->keys()->all())
            ->whereNull('off_duty')
            ->with(['position:id,title', 'person:id,callsign,person_photo_id', 'person.person_photo'])
            ->get()
            ->groupBy('position_id');

        if ($onDuty->isEmpty()) {
            return [];
        }

        $groups = [];
        foreach (self::SHIFT_COMMAND_PERSONNEL as $positionGroup) {
            $people = [];
            foreach ($positionGroup['positions'] as $positionId) {
                $position = $positionsById->get($positionId);
                if (!$position) {
                    continue;
                }

                $working = $onDuty->get($positionId);
                if (!$working) {
                    continue;
                }

                foreach ($working as $entry) {
                    $person = $entry->person;
                    $position = $entry->position;
                    $people[] = [
                        'id' => $person->id,
                        'callsign' => $person->callsign,
                        'photo_url' => $person->approvedPhoto()?->image_url,
                        'position' => [
                            'id' => $position->id,
                            'title' => $position->title,
                        ]
                    ];
                }

                usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));
            }

            $groups[] = [
                'title' => $positionGroup['title'],
                'people' => $people
            ];
        }

        return [
            'now' => (string) now(),
            'groups' => $groups
        ];
    }

    public static function retrievePositions(): Collection
    {
        $ids = [];
        foreach (self::SHIFT_COMMAND_PERSONNEL as $group) {
            $ids = array_merge($ids, $group['positions']);
        }

        return Position::whereIn('id', $ids)->get()->keyBy('id');
    }

}