<?php

namespace App\Lib;

use App\Models\Person;
use App\Models\Position;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProspectiveNewVolunteer
{
    /**
     * Find prospectives who have been trained.
     *
     * @return array
     */

    public static function retrievePotentialAlphas(): array
    {
        $year = current_year();
        $prospectives = Person::where('status', Person::PROSPECTIVE)->get();
        $trained = self::retrieveTrainedProspectives($prospectives, $year);
        $people = Alpha::buildAlphaInformation(collect($trained), $year);
        $potentials = [];
        foreach ($people as $person) {
            // Alpha::buildAlphaInformation re-derives `trained` from Training history, which can
            // disagree with trainee_status (different source). Keep only rows it also marks trained.
            if ($person->trained) {
                $potentials[] = $person;
            }
        }

        return $potentials;
    }

    /**
     * Convert a list of given prospectives to Alphas
     *
     * @param array $ids
     * @return array
     * @throws \Throwable
     */

    public static function convertProspectivesToAlphas(array $ids): array
    {
        $year = current_year();

        return DB::transaction(function () use ($ids, $year): array {
            $people = Person::where('status', Person::PROSPECTIVE)
                ->whereIntegerInRaw('id', $ids)
                ->orderBy('callsign')
                ->lockForUpdate()
                ->get();

            if ($people->isEmpty()) {
                return [];
            }

            $potentialAlphas = self::retrieveTrainedProspectives($people, $year);

            $alphaIds = [];
            foreach ($potentialAlphas as $potentialAlpha) {
                $potentialAlpha->status = Person::ALPHA;
                $potentialAlpha->auditReason = 'Mentor bulk convert';
                $potentialAlpha->saveWithoutValidation();
                $alphaIds[] = $potentialAlpha->id;
            }

            return $alphaIds;
        });
    }

    /**
     * Find the prospectives who have been trained.
     *
     * @param Collection $people
     * @param int|null $year Training year to check; defaults to current_year().
     * @return array
     */

    public static function retrieveTrainedProspectives(Collection $people, ?int $year = null): array
    {
        if ($people->isEmpty()) {
            return [];
        }

        $year ??= current_year();

        $slotIds = Slot::where('position_id', Position::TRAINING)
            ->where('begins_year', $year)
            ->pluck('id')
            ->toArray();

        if (empty($slotIds)) {
            return [];
        }

        $rows = DB::table('trainee_status')
            ->select('person_id')
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->whereIntegerInRaw('person_id', $people->pluck('id')->toArray())
            ->where('passed', true)
            ->groupBy('person_id')
            ->get();

        $prospectivesById = $people->keyBy('id');

        $potentials = [];
        foreach ($rows as $row) {
            $potentials[] = $prospectivesById[$row->person_id];
        }

        usort($potentials, fn($a, $b) => strcasecmp($a->callsign, $b->callsign));

        return $potentials;
    }
}