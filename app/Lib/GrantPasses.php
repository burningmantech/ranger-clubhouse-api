<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\Person;
use App\Models\Position;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GrantPasses
{
    /**
     * Find the Alphas and grant a WAP.
     *
     * @return mixed
     */

    public static function grantWAPsToAlphas(): mixed
    {
        $year = current_year();

        $accessDate = setting('TAS_DefaultAlphaWAPDate', true);

        // Where be my Alphas yo?
        $alphaIds = DB::table('person')
            ->select('id')
            ->where('status', Person::ALPHA)
            ->get()
            ->pluck('id');

        // Find all training slots
        $slotIds = DB::table('slot')
            ->select('id')
            ->whereYear('begins', $year)
            ->where('position_id', Position::TRAINING)
            ->get()
            ->pluck('id');

        if (!empty($slotIds)) {
            $prospectiveIds = DB::table('person_slot')
                ->select('person_id')
                ->join('person', 'person.id', 'person_slot.person_id')
                ->whereIntegerInRaw('slot_id', $slotIds)
                ->whereIn('status', [Person::PROSPECTIVE, Person::ALPHA])
                ->groupBy('person_id')
                ->get()
                ->pluck('person_id');
        } else {
            $prospectiveIds = [];
        }

        $ids = $alphaIds->merge($prospectiveIds)->unique();

        if (empty($ids)) {
            return [];
        }

        $people = Person::select('id', 'callsign', 'status')
            ->whereIntegerInRaw('id', $ids)
            ->whereNotExists(function ($q) {
                $q->selectRaw(1)
                    ->from('access_document')
                    ->whereColumn('person_id', 'person.id')
                    ->where('access_document.type', AccessDocument::WAP)
                    ->whereIn('access_document.status',
                        [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
                    ->limit(1);
            })->orderBy('callsign')
            ->get();

        self::grantAccessDocumentToPeople($people, AccessDocument::WAP, $accessDate, $year, AccessDocument::CLAIMED);

        return $people;
    }

    /**
     * Grant WAPs to Rangers who will be working.
     *
     * @return mixed
     */

    public static function grantWAPsToRangers(): mixed
    {
        $people = self::findRangersWhoNeedWAPs();
        self::grantAccessDocumentToPeople($people, AccessDocument::WAP, setting('TAS_DefaultWAPDate'), current_year());

        return $people;
    }

    /**
     * Find Rangers who should get WAPs
     *
     * @return mixed
     */

    public static function findRangersWhoNeedWAPs(): mixed
    {
        $year = current_year();
        if ($year == 2022) {
            $startYear = 2017;
        } else if ($year == 2023) {
            $startYear = 2018;
        } else if ($year == 2024) {
            $startYear = 2019;
        } else {
            $startYear = $year - 3;
        }

        // Find everyone who worked in the last three years
        $timesheetIds = DB::table('timesheet')
            ->whereYear('on_duty', '>=', $startYear)
            ->groupBy('person_id')
            ->pluck('person_id');

        $workedIds = DB::table('person')
            ->whereIntegerInRaw('id', $timesheetIds)
            ->whereIn('status', [Person::ACTIVE, Person::INACTIVE])
            ->pluck('id');

        // .. and find everyone signed up this year.
        $slotIds = DB::table('slot')->whereYear('begins', $year)->pluck('id');
        $signUpIds = DB::table('person_slot')
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->groupBy('person_id')
            ->pluck('person_id');

        $signUpIds = DB::table('person')
            ->whereIntegerInRaw('id', $signUpIds)
            ->whereIn('status', Person::ACTIVE_STATUSES)
            ->pluck('id');

        $personIds = $signUpIds->merge($workedIds)->unique()->toArray();

        // Pull any WAPs (qualified, claimed, submitted) or RPT & SCs (qualified, claimed, submitted, banked)
        $accessDocuments = DB::table('access_document')
            ->whereIntegerInRaw('person_id', $personIds)
            ->where(function ($check) {
                $check->where(function ($wap) {
                    $wap->where('type', AccessDocument::WAP)
                        ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::SUBMITTED]);
                });
                $check->orWhere(function ($ticket) {
                    $ticket->whereIn('type', [AccessDocument::RPT, AccessDocument::STAFF_CREDENTIAL])
                        ->whereIn('status', AccessDocument::CHECK_STATUSES);
                });
            })->get()
            ->groupBy('person_id');

        $potentials = DB::table('person')
            ->select('person.id', 'person.callsign', 'person.status')
            ->whereIntegerInRaw('person.id', $personIds)
            ->orderBy('callsign')
            ->get();

        $people = [];
        foreach ($potentials as $person) {
            $id = $person->id;

            $docs = $accessDocuments->get($id);
            if (!$docs) {
                // Gotta have at least a RPT
                continue;
            }

            $wap = $docs->firstWhere('type', AccessDocument::WAP);
            if ($wap) {
                // Has a WAP already, nothing to see here.
                continue;
            }

            $rpt = $docs->firstWhere('type', AccessDocument::RPT);
            $sc = $docs->firstWhere('type', AccessDocument::STAFF_CREDENTIAL);
            if ($rpt && !$sc) {
                // Has to have a RPT in order to get a WAP.
                $people[] = $person;
            }
        }

        return $people;
    }

    /**
     * Grant Vehicle Passes to people who have existing tickets
     *
     * @return mixed
     */

    public static function grantVehiclePasses(): mixed
    {
        $year = current_year();

        $ids = DB::table('access_document')
            ->select('person_id')
            ->whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::RPT])
            ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::BANKED])
            ->whereRaw('NOT EXISTS (SELECT 1 FROM access_document ad WHERE ad.person_id=access_document.person_id AND ad.type="vehicle_pass" AND ad.status IN ("qualified", "claimed", "submitted") LIMIT 1)')
            ->groupBy('person_id')
            ->pluck('person_id');

        if (!$ids->count()) {
            return [];
        }

        $people = Person::select('id', 'callsign', 'status')
            ->whereIntegerInRaw('id', $ids)
            ->whereNotIn('status', [Person::DISMISSED, Person::DECEASED])
            ->orderBy('callsign')
            ->get();

        self::grantAccessDocumentToPeople($people, AccessDocument::VEHICLE_PASS, null, $year);
        return $people;
    }

    /**
     * Grant an access pass to a list of people
     *
     * @param $people
     * @param $type
     * @param $accessDate
     * @param $year
     * @param $status
     * @return void
     */

    public static function grantAccessDocumentToPeople($people, $type, $accessDate, $year, $status = AccessDocument::QUALIFIED)
    {
        $user = Auth::user();
        $userId = $user?->id;

        foreach ($people as $person) {
            $ad = new AccessDocument([
                'person_id' => $person->id,
                'type' => $type,
                'status' => $status,
                'source_year' => $year,
                'expiry_date' => "$year-09-15",
                'access_date' => $accessDate,
            ]);
            $ad->addComment('created via maintenance function', $user);
            $ad->saveWithoutValidation();
            AccessDocumentChanges::log($ad, $userId, $ad, 'create');
        }
    }
}