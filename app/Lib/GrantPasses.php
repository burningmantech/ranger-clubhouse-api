<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\AccessDocumentChanges;
use App\Models\ActionLog;
use App\Models\Person;
use App\Models\PersonSlot;
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
            ->where('begins_year', $year)
            ->where('position_id', Position::TRAINING)
            ->get()
            ->pluck('id');

        if ($slotIds->isNotEmpty()) {
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

        if ($ids->isEmpty()) {
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
        list ($people, $startYear) = self::findRangersWhoNeedWAPs();
        self::grantAccessDocumentToPeople($people, AccessDocument::WAP, setting('TAS_DefaultWAPDate'), current_year());

        return [$people, $startYear];
    }

    /**
     * Find Rangers who should get WAPs
     *
     * 1. Find active & inactives who worked in the last 3 events. (pandemic years adjusted for.)
     * 2. Find active, inactives, inactive extends, and retirees who are signed up this year.
     * 3. Merge #1 & #2, and pull their WAP, SPT, and Staff Credential if present.
     * 4. Run through the combined #1 & #2 lists and report on potential people to give WAPs to whom:
     *      - Do not already have a WAP or SC
     *              AND
     *      - Have a Special Price Ticket OR signed up this year.
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
        $slotIds = DB::table('slot')->where('begins_year', $year)->pluck('id');
        $signUpIds = DB::table('person_slot')
            ->whereIntegerInRaw('slot_id', $slotIds)
            ->groupBy('person_id')
            ->pluck('person_id');

        $signUpIds = DB::table('person')
            ->whereIntegerInRaw('id', $signUpIds)
            ->whereIn('status', Person::ACTIVE_STATUSES)
            ->pluck('id');

        $personIds = $signUpIds->merge($workedIds)->unique()->toArray();

        // Pull any WAPs (qualified, claimed, submitted) or SPT & SCs (qualified, claimed, submitted, banked)
        $accessDocuments = AccessDocument::whereIntegerInRaw('person_id', $personIds)
            ->where(function ($check) {
                $check->where(function ($wap) {
                    $wap->where('type', AccessDocument::WAP)
                        ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::SUBMITTED]);
                });
                $check->orWhere(function ($ticket) {
                    $ticket->whereIn('type', [AccessDocument::SPT, AccessDocument::STAFF_CREDENTIAL])
                        ->whereIn('status', AccessDocument::CURRENT_STATUSES);
                });
            })->get()
            ->groupBy('person_id');

        $potentials = DB::table('person')
            ->select('id', 'callsign', 'status')
            ->whereIntegerInRaw('id', $personIds)
            ->orderBy('callsign')
            ->get();

        $people = [];
        foreach ($potentials as $person) {
            $id = $person->id;
            $person->has_rpt = false;
            $person->tickets = [];
            $person->years = [];

            $docs = $accessDocuments->get($id);
            if (!$docs) {
                if ($signUpIds->contains($id)) {
                    // No tickets or WAPs, yet they are signed up to work. WAP 'em.
                    $people[] = $person;
                    $person->schedule = self::retrieveScheduleForPerson($id);
                    $person->years = self::retrieveYearsWorkedSince($id, $startYear);
                }
                continue;
            }

            $wap = $docs->firstWhere('type', AccessDocument::WAP);
            if ($wap) {
                // Has a WAP already, nothing to see here.
                continue;
            }

            $sc = $docs->firstWhere('type', AccessDocument::STAFF_CREDENTIAL);
            if ($sc) {
                // Has SC which is a WAP as well, nothing to see here too.
                continue;
            }

            $spt = $docs->firstWhere('type', AccessDocument::SPT);
            if ($spt) {
                // have an existing SPT, WAP 'em.
                $people[] = $person;
                $person->schedule = self::retrieveScheduleForPerson($id);
                $person->has_rpt = true;
                $person->tickets = $docs;
                $person->years = self::retrieveYearsWorkedSince($id, $startYear);
                continue;
            }

            if ($signUpIds->contains($id)) {
                // No ticket, yet they are signed up to work. WAP 'em.
                $people[] = $person;
                $person->schedule = self::retrieveScheduleForPerson($id);
                $person->tickets = $docs;
                $person->years = self::retrieveYearsWorkedSince($id, $startYear);
            }
        }

        return [$people, $startYear];
    }

    /**
     * Find the years worked since a given year.
     *
     * @param int $personId
     * @param int $startYear
     * @return array
     */

    public static function retrieveYearsWorkedSince(int $personId, int $startYear): array
    {
        $sql = DB::table('timesheet')
            ->selectRaw("YEAR(on_duty) as year")
            ->whereYear('on_duty', '>=', $startYear)
            ->where('person_id', $personId)
            ->groupBy("year")
            ->orderBy("year", "asc");

        return $sql->pluck('year')->toArray();
    }

    /**
     * Retrieve the schedule for a person
     *
     * @param int $personId
     * @return array
     */

    public static function retrieveScheduleForPerson(int $personId): array
    {
        $signUps = PersonSlot::join('slot', 'slot.id', 'person_slot.slot_id')
            ->where('person_id', $personId)
            ->where('slot.begins_year', current_year())
            ->with(['slot:id,description,begins,position_id', 'slot.position:id,title'])
            ->get();

        $signUps = $signUps->sortBy('slot.begins')->values();

        $schedule = [];
        foreach ($signUps as $signUp) {
            $schedule[] = (object)[
                'id' => $signUp->slot_id,
                'position_title' => $signUp->slot->position->title ?? "position #{$signUp->slot->position_id}",
                'description' => $signUp->slot->description,
                'begins' => $signUp->slot->begins->format('Y-m-d H:i'),
            ];
        }

        return $schedule;
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
            ->whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::SPT])
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

    public static function grantAccessDocumentToPeople($people, $type, $accessDate, $year, $status = AccessDocument::QUALIFIED): void
    {
        $user = Auth::user();
        $userId = Auth::id();

        $documents = [];
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
            AccessDocumentChanges::log($ad, $userId, $ad, AccessDocumentChanges::OP_CREATE);
            $documents[] = [
                'id' => $ad->id,
                'person_id' => $person->id,
            ];
        }

        if (!empty($documents)) {
            ActionLog::record($user, 'access-document-bulk-grant', 'bulk grant', [
                'type' => $type,
                'documents' => $documents
            ]);
        }
    }
}