<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkUpload\MealPass;
use App\Lib\BulkUpload\Record;
use App\Lib\BulkUploader;
use App\Models\Provision;

class ProvisionHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        [$defaultSourceYear, $defaultExpiryYear] = BulkUploader::defaultYears(true);
        $year = current_year();

        $isAllocated = str_starts_with($action, 'alloc_');
        $type = $isAllocated ? str_replace('alloc_', '', $action) : $action;
        if ($isAllocated) {
            $defaultSourceYear = $year;
        }

        $mealPass = MealPass::tryFrom($type);
        $isMeals = $mealPass !== null;
        $isEventRadio = $type === Provision::EVENT_RADIO;

        if (!$isMeals && $type !== Provision::WET_SPOT && !$isEventRadio) {
            throw new UnacceptableConditionException('Unknown provision type');
        }

        $existingType = $isMeals ? Provision::MEALS : $type;
        $periods = $mealPass?->periods() ?? ['pre' => false, 'event' => false, 'post' => false];

        $existingByPerson = $this->loadExistingProvisions(
            $records,
            $existingType,
            $isAllocated,
            $isMeals,
            $periods,
        );

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $existing = $existingByPerson[$person->id] ?? null;

            if ($existing && !$commit) {
                $description = $isAllocated
                    ? 'Already has ' . $existing->getTypeLabel() . ' allocated provision. Existing item will be cancelled and replaced.'
                    : 'Has ' . $existing->getTypeLabel() . ' earned year ' . $existing->source_year . '. Existing item will be cancelled and replaced.';
                $record->warn($description);
                continue;
            }

            $record->succeed();
            $sourceYear = $defaultSourceYear;
            $expiryYear = $defaultExpiryYear;
            $itemCount = 0;

            $data = $record->data;
            $fieldCount = count($data);

            if ($isEventRadio) {
                if ($fieldCount > 0) {
                    $raw = array_shift($data);
                    if (!is_numeric($raw)) {
                        $record->fail('Item count is not a number');
                        continue;
                    }
                    $itemCount = (int)$raw;
                    $fieldCount--;
                } else {
                    $itemCount = 1;
                }
            }

            if ($isAllocated) {
                if ($fieldCount >= 1) {
                    $record->fail('Allocated provisions uploads only take a callsign and no other parameters.');
                    continue;
                }
            } else {
                if ($fieldCount >= 1 && !BulkUploader::checkYearRange($sourceYear, $data[0], $record, true)) {
                    continue;
                }
                if ($fieldCount >= 2 && !BulkUploader::checkYearRange($expiryYear, $data[1], $record, false)) {
                    continue;
                }
            }

            if ($expiryYear < $sourceYear) {
                $record->fail("Source year [$sourceYear] is after expiry year [$expiryYear]");
                continue;
            }

            if (!$commit) {
                continue;
            }

            $provision = new Provision([
                'person_id' => $person->id,
                'type' => $isMeals ? Provision::MEALS : $type,
                'status' => Provision::AVAILABLE,
                'expires_on' => $expiryYear,
                'source_year' => $sourceYear,
                'is_allocated' => $isAllocated,
                'additional_comments' => 'created via bulk uploader',
            ]);

            if ($isEventRadio) {
                $provision->item_count = $itemCount;
            } elseif ($isMeals) {
                $provision->pre_event_meals = $periods['pre'];
                $provision->event_week_meals = $periods['event'];
                $provision->post_event_meals = $periods['post'];
            }

            $provision->auditReason = 'created via bulk upload';
            BulkUploader::saveModel($provision, $record);

            if (!$existing) {
                continue;
            }

            $existing->status = Provision::CANCELLED;
            $existing->additional_comments = $existing->auditReason = 'Replaced by item #' . $provision->id . ' via bulk uploader';
            $record->warn(
                'Existing provision RP-' . $existing->id . ' ' . $existing->getTypeLabel()
                . ' cancelled and replaced with RP-' . $provision->id . ' ' . $provision->getTypeLabel(),
            );
            $existing->saveWithoutValidation();
        }
    }

    /**
     * Bulk-load the existing provision (if any) per person, matching the
     * semantics of Provision::findAvailableTypeForPerson and
     * Provision::findAvailableMealsForPerson.
     *
     * @param list<Record> $records
     * @param array{pre: bool, event: bool, post: bool} $periods
     * @return array<int, Provision>
     */
    private function loadExistingProvisions(
        array $records,
        string $existingType,
        bool $isAllocated,
        bool $isMeals,
        array $periods,
    ): array {
        $personIds = array_values(array_filter(array_map(
            fn (Record $r) => $r->person?->id,
            $records,
        )));
        if (empty($personIds)) {
            return [];
        }

        $sql = Provision::whereIn('person_id', $personIds)
            ->where('type', $existingType)
            ->whereIn('status', Provision::CURRENT_STATUSES)
            ->where('is_allocated', $isAllocated);

        if ($isMeals) {
            $sql->where('pre_event_meals', $periods['pre'])
                ->where('event_week_meals', $periods['event'])
                ->where('post_event_meals', $periods['post']);
        }

        return $sql->get()->keyBy('person_id')->all();
    }
}
