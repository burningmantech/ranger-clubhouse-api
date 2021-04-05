<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Position;
use App\Models\Slot;
use App\Models\TraineeStatus;

use Illuminate\Support\Facades\DB;

class BMIDManagement
{
    // Person columns to return when doing a BMID sanity check
    const INSANE_PERSON_COLUMNS = [
        'id',
        'callsign',
        'email',
        'first_name',
        'last_name',
        'status'
    ];

     /**
     * Sanity check the BMIDs.
     *
     * - Find any BMID for a person who has non-Training shifts starting before the access date
     * - Find any BMID for a person who does not have a WAP or a Staff Credential
     * - Find any BMID for a person who has reduced-price-ticket and a WAP with an access date before the box office opens.
     *
     * @param int $year
     * @return array[]
     */

    public static function sanityCheckForYear(int $year): array
    {
        /*
         * Find people who have signed up shifts starting before their WAP access date
         */

        $ids = DB::table('person_slot')
            ->select('person_slot.person_id')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->join('position', 'position.id', 'slot.position_id')
            ->join('access_document', 'access_document.person_id', 'person_slot.person_id')
            ->whereYear('slot.begins', $year)
            ->whereNotIn('slot.position_id', [Position::TRAINING, Position::TRAINER, Position::TRAINER_ASSOCIATE, Position::TRAINER_UBER])
            ->whereIn('access_document.type', [AccessDocument::WAP, AccessDocument::STAFF_CREDENTIAL])
            ->whereIn('access_document.status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::BANKED])
            ->where('slot.begins', '>', "$year-08-15")
            ->where('access_document.access_any_time', false)
            ->groupBy('person_slot.person_id')
            ->pluck('person_slot.person_id');

        $shiftsBeforeWap = Person::whereIn('id', $ids)
            ->where('status', '!=', Person::ALPHA)
            ->orderBy('callsign')
            ->get(self::INSANE_PERSON_COLUMNS);

        /*
         * Find people who signed up early shifts yet do not have a WAP
         */

        $ids = DB::table('person_slot')
            ->select('person_slot.person_id')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->join('position', 'position.id', 'slot.position_id')
            ->whereYear('slot.begins', $year)
            ->where('slot.begins', '>', "$year-08-10")
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('access_document')
                    ->whereColumn('access_document.person_id', 'person_slot.person_id')
                    ->whereIn('type', [AccessDocument::WAP, AccessDocument::STAFF_CREDENTIAL])
                    ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::BANKED]);
            })->groupBy('person_slot.person_id')
            ->pluck('person_slot.person_id');

        $shiftsNoWap = Person::whereIn('id', $ids)
            ->where('status', '!=', Person::ALPHA)
            ->orderBy('callsign')
            ->get(self::INSANE_PERSON_COLUMNS);

        $boxOfficeOpenDate = setting('TAS_BoxOfficeOpenDate', true);

        $ids = DB::table('access_document as wap')
            ->join('access_document as rpt', function ($j) {
                $j->on('wap.person_id', 'rpt.person_id')
                    ->where('rpt.type', AccessDocument::RPT)
                    ->whereIn('rpt.status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED]);
            })->join('person', function ($j) {
                $j->on('person.id', 'wap.person_id')
                    ->where('person.status', '!=', Person::ALPHA);
            })->where('wap.type', AccessDocument::WAP)
            ->whereIn('wap.status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED])
            ->where('wap.access_date', '<', $boxOfficeOpenDate)
            ->groupBy('wap.person_id')
            ->pluck('wap.person_id');

        $rptBeforeBoxOfficeOpens = Person::whereIn('id', $ids)
            ->orderBy('callsign')
            ->get(self::INSANE_PERSON_COLUMNS);

        return [
            [
                'type' => 'shifts-before-access-date',
                'people' => $shiftsBeforeWap,
            ],

            [
                'type' => 'shifts-no-wap',
                'people' => $shiftsNoWap,
            ],

            [
                'type' => 'rpt-before-box-office',
                'box_office' => $boxOfficeOpenDate,
                'people' => $rptBeforeBoxOfficeOpens
            ]
        ];
    }

    /**
     * Retrieve a category of BMIDs to manage
     *
     * 'alpha': All status Prospective & Alpha
     * 'signedup': Current Rangers who are signed up for a shift starting Aug 10th or later
     * 'submitted':  status submitted BMIDs
     * 'printed': status printed BMIDs
     * 'nonprint': status issues and/or do-not-print BMIDs
     * default: any BMIDs with showers, meals, any access time, or a WAP date prior to the WAP default.
     *
     * @param int $year
     * @param string $filter
     * @return Bmid[]|array|\Illuminate\Database\Eloquent\Collection
     */

    public static function retrieveCategoryToManage(int $year, string $filter)
    {
        switch ($filter) {
            case 'alpha':
                // Find all alphas & prospective
                $ids = Person::whereIn('status', [Person::ALPHA, Person::PROSPECTIVE])
                    ->get('id')
                    ->pluck('id');
                break;

            case 'signedup':
                // Find any vets who are signed up and/or passed training
                $slotIds = Slot::whereYear('begins', $year)
                    ->where('begins', '>=', "$year-08-10")
                    ->pluck('id');

                $signedUpIds = PersonSlot::whereIn('slot_id', $slotIds)
                    ->join('person', function ($j) {
                        $j->whereRaw('person.id=person_slot.person_id');
                        $j->whereIn('person.status', Person::ACTIVE_STATUSES);
                    })
                    ->distinct('person_slot.person_id')
                    ->pluck('person_id')
                    ->toArray();

                $slotIds = Slot::join('position', 'position.id', '=', 'slot.position_id')
                    ->whereYear('begins', $year)
                    ->where('position.type', Position::TYPE_TRAINING)
                    ->get(['slot.id'])
                    ->pluck('id')
                    ->toArray();

                $trainedIds = TraineeStatus::join('person', function ($j) {
                    $j->whereRaw('person.id=trainee_status.person_id');
                    $j->whereIn('person.status', Person::ACTIVE_STATUSES);
                })
                    ->whereIn('slot_id', $slotIds)
                    ->where('passed', 1)
                    ->distinct('trainee_status.person_id')
                    ->pluck('trainee_status.person_id')
                    ->toArray();
                $ids = array_merge($trainedIds, $signedUpIds);
                break;

            case 'submitted':
            case 'printed':
                // Any BMIDs already submitted or printed out
                $ids = Bmid::where('year', $year)
                    ->where('status', $filter)
                    ->pluck('person_id')
                    ->toArray();
                break;

            case 'nonprint':
                // Any BMIDs held back
                $ids = Bmid::where('year', $year)
                    ->whereIn('status', [BMID::ISSUES, BMID::DO_NOT_PRINT])
                    ->pluck('person_id')
                    ->toArray();
                break;

            case 'no-shifts':
                // Any BMIDs held back
                $ids = Bmid::where('year', $year)
                    ->whereRaw("NOT EXISTS (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id WHERE bmid.person_id=person_slot.person_id AND YEAR(slot.begins)=$year LIMIT 1)")
                    ->pluck('person_id')
                    ->toArray();
                break;

            default:
                // Find the special people.
                // "You're good enough, smart enough, and doggone it, people like you."
                $wapDate = setting('TAS_DefaultWAPDate');

                $specialIds = BMID::where('year', $year)
                    ->where(function ($q) {
                        $q->whereNotNull('title1');
                        $q->orWhereNotNull('title2');
                        $q->orWhereNotNull('title3');
                        $q->orWhereNotNull('meals');
                        $q->orWhere('showers', true);
                        $q->orWhereExists(function ($item) {
                           $item->select(DB::raw(1))
                               ->from('access_document')
                               ->whereColumn('access_document.person_id', 'bmid.person_id')
                               ->whereIn('access_document.type', [ AccessDocument::WET_SPOT, ...AccessDocument::EAT_PASSES])
                               ->whereIn('access_document.status', [ AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
                               ->limit(1);
                        });
                    })
                    ->get(['person_id'])
                    ->pluck('person_id')
                    ->toArray();

                $adIds = AccessDocument::whereIn('type', [AccessDocument::STAFF_CREDENTIAL, AccessDocument::WAP])
                    ->whereIn('status', [
                        AccessDocument::BANKED,
                        AccessDocument::QUALIFIED,
                        AccessDocument::CLAIMED,
                        AccessDocument::SUBMITTED
                    ])->where(function ($q) use ($wapDate) {
                        // Any AD where the person can get in at any time
                        //   OR
                        // The access date is lte WAP access
                        $q->where('access_any_time', 1);
                        $q->orWhere(function ($q) use ($wapDate) {
                            $q->whereNotNull('access_date');
                            $q->where('access_date', '<', "$wapDate 00:00:00");
                        });
                    })
                    ->distinct('person_id')
                    ->get(['person_id'])
                    ->pluck('person_id')
                    ->toArray();

                $ids = array_merge($specialIds, $adIds);
                break;
        }

        return BMID::findForPersonIds($year, $ids);
    }
}