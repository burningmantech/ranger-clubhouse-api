<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Slot;

class LinkSlots
{
    const string SUCCESS = 'success';
    const string NOT_CHILD = 'not-child';
    const string NO_PARENT_SLOT = 'no-parent-slot';
    const string MULTIPLE_SLOTS_FOUND = 'multiple-slots';

    const string TYPE_MULTIPLIER = 'multiplier';
    const string TYPE_PARENT = 'parent';

    public static function execute(array $slotIds, string $type, bool $commit): array
    {
        if ($type != self::TYPE_MULTIPLIER && $type != self::TYPE_PARENT) {
            throw new UnacceptableConditionException("type is not known.");
        }

        if (empty($slotIds)) {
            return [];
        }

        $slots = Slot::whereIntegerInRaw('id', $slotIds)
            ->with('position')
            ->orderBy('begins')
            ->get();

        $linked = [];
        foreach ($slots as $slot) {
            $parentPositionId = $slot->position->parent_position_id;

            $result = [
                'id' => $slot->id,
                'begins' => (string)$slot->begins,
                'description' => $slot->description,
            ];

            if (!$parentPositionId) {
                // Slot is for a position that is not a child.
                $result['status'] = self::NOT_CHILD;
                $linked[] = $result;
                continue;
            }

            $parentSlots = Slot::where('position_id', $parentPositionId)
                ->where('begins', $slot->begins)
                ->with('position:id,title')
                ->get();

            $result['type'] = $type;

            if ($parentSlots->isEmpty()) {
                // Nobody found.
                $result['status'] = self::NO_PARENT_SLOT;
                $linked[] = $result;
                continue;
            }

            if ($parentSlots->count() > 1) {
                // Too many found!
                $result['status'] = self::MULTIPLE_SLOTS_FOUND;
                $result['slots'] = $parentSlots->map(fn($s) => [
                    'id' => $s->id,
                    'begins' => $s->begins,
                    'description' => $s->description,
                    'position_title' => $s->position->title,
                ])->toArray();
                $linked[] = $result;
                continue;
            }

            $parentSlot = $parentSlots->first();
            $result['linked_slot'] = [
                'id' => $parentSlot->id,
                'description' => $parentSlot->description,
                'position_title' => $parentSlot->position->title
            ];

            if ($commit) {
                // Ensure someone did not click the wrong button.
                if ($type === self::TYPE_MULTIPLIER) {
                    $slot->trainer_slot_id = $parentSlot->id;
                    $slot->parent_signup_slot_id = null;
                } else {
                    $slot->parent_signup_slot_id = $parentSlot->id;
                    $slot->trainer_slot_id = null;
                }
                $slot->auditReason = "slot link";
                $slot->saveWithoutValidation();
            }
            $result['status'] = self::SUCCESS;

            $linked[] = $result;
        }

        return $linked;
    }
}
