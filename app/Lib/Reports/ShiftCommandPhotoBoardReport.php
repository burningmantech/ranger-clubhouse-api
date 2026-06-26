<?php

namespace App\Lib\Reports;

use App\Models\Person;
use App\Models\Position;
use App\Models\Timesheet;

class ShiftCommandPhotoBoardReport
{
    const array SHIFT_COMMAND_PERSONNEL = [
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

    public static function execute(): array
    {
        $onDuty = Timesheet::whereIn('position_id', self::positionIds())
            ->whereNull('off_duty')
            ->with(['position:id,title', 'person:id,callsign,person_photo_id', 'person.person_photo'])
            ->get()
            ->groupBy('position_id');

        $groups = [];
        foreach (self::SHIFT_COMMAND_PERSONNEL as $positionGroup) {
            $people = [];
            foreach ($positionGroup['positions'] as $positionId) {
                $working = $onDuty->get($positionId);
                if (!$working) {
                    continue;
                }

                foreach ($working as $entry) {
                    $people[] = self::personEntry($entry->person, $entry->position);
                }
            }

            usort($people, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

            $groups[] = [
                'title' => $positionGroup['title'],
                'people' => $people
            ];
        }

        $hosts = [];
        foreach ($onDuty->get(Position::ROC_STAR) ?? [] as $entry) {
            $hosts[] = self::personEntry($entry->person);
        }
        usort($hosts, fn($a, $b) => strcasecmp($a['callsign'], $b['callsign']));

        return [
            'now' => (string)now(),
            'hosts' => $hosts,
            'groups' => $groups
        ];
    }

    /**
     * Build a person card for the board, optionally tagged with the position worked.
     *
     * @return array{id: int, callsign: string, photo_url: ?string, position?: array{id: int, title: string}}
     */
    private static function personEntry(Person $person, ?Position $position = null): array
    {
        $entry = [
            'id' => $person->id,
            'callsign' => $person->callsign,
            'photo_url' => $person->approvedPhoto()?->image_url,
        ];

        if ($position) {
            $entry['position'] = [
                'id' => $position->id,
                'title' => $position->title,
            ];
        }

        return $entry;
    }

    /**
     * Every position id that can appear on the board, including the ROC_STAR hosts.
     *
     * @return array<int>
     */
    private static function positionIds(): array
    {
        $ids = [Position::ROC_STAR];
        foreach (self::SHIFT_COMMAND_PERSONNEL as $group) {
            $ids = array_merge($ids, $group['positions']);
        }

        return $ids;
    }
}
