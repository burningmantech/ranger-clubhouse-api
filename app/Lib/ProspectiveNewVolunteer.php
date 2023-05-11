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
        $prospectives = Person::where('status', Person::PROSPECTIVE)->get();
        $trained = self::retrieveTrainedProspectives($prospectives);
        $people = Alpha::buildAlphaInformation(collect($trained), current_year());
        $potentials = [];
        foreach ($people as $person) {
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
     */

    public static function convertProspectivesToAlphas(array $ids): array
    {
        $people = Person::where('status', Person::PROSPECTIVE)
            ->whereIntegerInRaw('id', $ids)
            ->orderBy('callsign')
            ->get();

        if ($people->isEmpty()) {
            return [];
        }

        $potentialAlphas = self::retrieveTrainedProspectives($people);

        $alphaIds = [];
        foreach ($potentialAlphas as $potentialAlpha) {
            $potentialAlpha->changeStatus(Person::ALPHA, $potentialAlpha->status, 'Mentor bulk convert');
            $potentialAlpha->auditReason = 'Mentor bulk convert';
            $potentialAlpha->saveWithoutValidation();
            $alphaIds[] = $potentialAlpha->id;
        }

        return $alphaIds;
    }

    /**
     * Find the prospectives who have been trained.
     *
     * @param Collection $people
     * @return array
     */

    public static function retrieveTrainedProspectives(Collection $people): array
    {
        if ($people->isEmpty()) {
            return [];
        }

        $slotIds = Slot::where('position_id', Position::TRAINING)
            ->where('begins_year', current_year())
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