<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\Position;
use App\Models\PersonEvent;

use Illuminate\Support\Facades\DB;

use App\Helpers\SqlHelper;

class Bmid extends ApiModel
{
    const MEALS_TYPES = [
        'all',
        'event',
        'event+post',
        'post',
        'pre',
        'pre+event',
        'pre+post'
    ];
    const READY_TO_PRINT_STATUSES = [
        'in_prep',
        'ready_to_print',
        'ready_to_reprint_changed',
        'ready_to_reprint_lost',
    ];
    const ALLOWED_PERSON_STATUSES = [
        'active',
        'inactive',
        'inactive extension',
        'retired',
        'alpha',
        'prospective'
    ];
    const PERSON_WITH = 'person:id,callsign,status,first_name,last_name,email,bpguid';
    const INSANE_PERSON_COLUMNS = [
        'id',
        'callsign',
        'email',
        'first_name',
        'last_name',
        'status'
    ];
    public $wap;

    // Allow (mostly) mass assignment - BMIDs are an exclusive Admin function.
    public $_access_any_time;
    public $_access_date;
    public $_original_access_any_time;
    public $_original_access_date;
    public $uploadedToLambase = false;
    public $has_signups = false;
    protected $table = 'bmid';
    protected $auditModel = true;
    protected $guarded = [
        'create_datetime',
        'modified_datetime'
    ];
    protected $attributes = [
        'showers' => false,
        'meals' => null,
        'org_vehicle_insurance' => false
    ];
    protected $casts = [
        'showers' => 'bool',
        'org_vehicle_insurance' => 'bool',
        'create_datetime' => 'datetime',
        'modified_datetime' => 'datetime',
        'access_date' => 'datetime',
        'access_any_time' => 'bool',
    ];
    protected $appends = [
        'access_any_time',
        'access_date',
        'wap_id',
        'wap_status',
        'wap_type',
        'has_signups'
    ];

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = 'in_prep';
            }
        });

        self::saved(function ($model) {
            $model->updateWap();
        });

        self::created(function ($model) {
            $model->updateWap();
        });
    }

    public static function find($id)
    {
        $row = self::where('id', $id)->first();
        if ($row) {
            $row->loadRelationships();
        }

        return $row;
    }

    public function loadRelationships()
    {
        $this->load(self::PERSON_WITH);
        $event = PersonEvent::findForPersonYear($this->person_id, $this->year);

        if ($event) {
            $this->org_vehicle_insurance = $event->org_vehicle_insurance;
        }

        $wap = AccessDocument::findWAPForPerson($this->person_id);
        if ($wap) {
            $this->setWap($wap);
        }
    }

    public function setWap($wap)
    {
        $this->_access_date = $this->_original_access_date = $wap->access_date;
        $this->_access_any_time = $this->_original_access_any_time = $wap->access_any_time;
        $this->wap = $wap;
    }

    public static function findOrFail($id)
    {
        $row = self::where('id', $id)->firstOrFail();
        if ($row) {
            $row->loadRelationships();
        }

        return $row;
    }

    public static function findForPersonYear($personId, $year)
    {
        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    public static function findForPersonManage($personId, $year)
    {
        $rows = self::findForPersonIds($year, [$personId]);
        return $rows[0];
    }

    public static function findForPersonIds($year, $personIds)
    {
        if (empty($personIds)) {
            return [];
        }

        // Bulk look up
        $bmids = Bmid::where('year', $year)->whereIn('person_id', $personIds)->get();
        $bmidsByPerson = $bmids->keyBy('person_id');

        // Figure out which people do not have BMIDs yet.
        foreach ($personIds as $personId) {
            if (!isset($bmidsByPerson[$personId])) {
                $bmid = new Bmid([
                    'person_id' => $personId,
                    'year' => $year,
                    'status' => 'in_prep'
                ]);

                $bmids->push($bmid);
                $bmidsByPerson[$personId] = $bmid;
            }
        }

        self::bulkLoadRelationships($bmids, $personIds);

        $bmids = $bmids->sortBy(function ($bmid, $key) {
            return $bmid->person ? $bmid->person->callsign : "";
        }, SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $bmids;
    }

    public static function bulkLoadRelationships($bmids, $personIds)
    {
        $year = current_year();

        // Populate all the BMIDS with people..
        $bmids->load([self::PERSON_WITH]);

        $personEvents = PersonEvent::findAllForIdsYear($bmids->pluck('person_id'), $year)->keyBy('person_id');
        foreach ($bmids as $bmid) {
            $event = $personEvents[$bmid->person_id] ?? null;
            if ($event) {
                $bmid->org_vehicle_insurance = $event->org_vehicle_insurance;
            }
        }

        $waps = AccessDocument::findWAPForPersonIds($personIds);
        $bmidsByPerson = $bmids->keyBy('person_id');
        foreach ($waps as $personId => $wap) {
            $bmidsByPerson[$personId]->setWap($wap);
        }


        // Figure out who has signed up for the year.
        $ids = DB::table('person')
            ->select('id')
            ->whereIn('id', $personIds)
            ->whereRaw("EXISTS (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id WHERE person.id=person_slot.person_id AND YEAR(slot.begins)=$year LIMIT 1)")
            ->get()
            ->pluck('id');

        foreach ($ids as $id) {
            $bmidsByPerson[$id]->has_signups = true;
        }
    }

    public static function firstOrNewForPersonYear($personId, $year)
    {
        $row = self::firstOrNew(['person_id' => $personId, 'year' => $year]);
        $row->loadRelationships();

        return $row;
    }

    public static function findForQuery($query)
    {
        $sql = self::query();

        if (isset($query['year'])) {
            $sql->where('year', $query['year']);
        }

        $bmids = $sql->with(['person:id,callsign,email'])->get();

        self::bulkLoadRelationships($bmids, $bmids->pluck('person_id')->toArray());

        return $bmids;
    }

    public static function findForManage($year, $filter)
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
                    ->whereIn('status', ['issues', 'do_not_print'])
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

                $specialIds = self::where('year', $year)
                    ->where(function ($q) {
                        $q->whereNotNull('title1');
                        $q->orWhereNotNull('title2');
                        $q->orWhereNotNull('title3');
                        $q->orWhereNotNull('meals');
                        $q->orWhere('showers', true);
                    })
                    ->get(['person_id'])
                    ->pluck('person_id')
                    ->toArray();

                $adIds = AccessDocument::whereIn('type', ['staff_credential', 'work_access_pass'])
                    ->whereIn('status', ['banked', 'qualified', 'claimed', 'submitted'])
                    ->where(function ($q) use ($wapDate) {
                        // Any AD where the person can get in at any time
                        //   OR
                        // The access date is gte WAP access
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

        return self::findForPersonIds($year, $ids);
    }

    public static function sanityCheckForYear($year)
    {
        /*
         * Find people who have signed up shifts starting before their WAP access date
         */

        $positionIds = implode(',', [Position::TRAINING, Position::TRAINER, Position::TRAINER_ASSOCIATE, Position::TRAINER_UBER]);
        $ids = DB::select("SELECT ps.person_id as id
            FROM person_slot ps
            JOIN slot s ON s.id=ps.slot_id
            JOIN position po ON po.id=s.position_id
            JOIN access_document wap ON wap.person_id=ps.person_id
            WHERE
                YEAR(s.begins)=$year
                AND s.position_id NOT IN ($positionIds)
                AND wap.type IN ('staff_credential', 'work_access_pass')
                AND wap.status in ('qualified', 'claimed', 'banked')
                AND s.begins > '$year-08-15'
                AND s.begins < wap.access_date
                AND wap.access_any_time=0
            GROUP BY ps.person_id");

        $shiftsBeforeWap = Person::whereIn('id', array_column($ids, 'id'))
            ->where('status', '!=', Person::ALPHA)
            ->orderBy('callsign')
            ->get(self::INSANE_PERSON_COLUMNS);
        /*
         * Find people who signed up early shifts yet do not have a WAP
         */

        $positionIds = implode(',', [Position::TRAINING, Position::TRAINER, Position::TRAINER_ASSOCIATE, Position::TRAINER_UBER, Position::GREEN_DOT_TRAINER, Position::GREEN_DOT_TRAINING]);
        $ids = DB::select("SELECT ps.person_id as id
                FROM person_slot ps
                JOIN slot s ON s.id=ps.slot_id
                JOIN position po ON po.id=s.position_id
                WHERE
                    YEAR(s.begins)=$year
                    AND s.position_id NOT IN ($positionIds)
                    AND YEAR(s.begins)=$year
                    AND s.begins > '$year-08-10'
                    AND NOT EXISTS (SELECT 1 FROM access_document wap
                        WHERE wap.person_id=ps.person_id
                        AND wap.type IN ('work_access_pass', 'staff_credential')
                        AND wap.status IN ('qualified', 'claimed', 'banked') LIMIT 1)
                GROUP BY ps.person_id");

        $shiftsNoWap = Person::whereIn('id', array_column($ids, 'id'))
            ->where('status', '!=', Person::ALPHA)
            ->orderBy('callsign')
            ->get(self::INSANE_PERSON_COLUMNS);

        $boxOfficeOpenDate = setting("TAS_BoxOfficeOpenDate");

        $ids = DB::select("SELECT wap.person_id AS id
                FROM access_document wap
                JOIN access_document rpt ON wap.person_id=rpt.person_id
                    AND rpt.type='reduced_price_ticket' AND rpt.status in ('qualified', 'claimed')
                JOIN person p ON p.id = wap.person_id AND p.status != 'alpha'
                WHERE
                    wap.type='work_access_pass'
                    AND wap.status IN ('qualified', 'claimed')
                    AND wap.access_date < '$boxOfficeOpenDate'
                GROUP BY wap.person_id");

        $rptBeforeBoxOfficeOpens = Person::whereIn('id', array_column($ids, 'id'))
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

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function updateWap()
    {
        AccessDocument::updateWAPsForPerson($this->person_id, $this->_access_date, $this->_access_any_time);

        $wap = $this->wap;
        if ($wap) {
            $wap->refresh();
            $this->setWap($wap);
        }
    }

    public function setTitle1Attribute($value)
    {
        $this->attributes['title1'] = $value ?: null;
    }

    public function setTitle2Attribute($value)
    {
        $this->attributes['title2'] = $value ?: null;
    }

    public function setTitle3Attribute($value)
    {
        $this->attributes['title3'] = $value ?: null;
    }

    public function setMealsAttribute($value)
    {
        $this->attributes['meals'] = $value ?: null;
    }

    public function setTeamAttribute($value)
    {
        $this->attributes['team'] = $value ?: null;
    }

    public function setAccessDateAttribute($value)
    {
        $this->_access_date = $value;
    }

    public function getAccessDateAttribute()
    {
        return (string)$this->_access_date;
    }

    public function setAccessAnyTimeAttribute($value)
    {
        $this->_access_any_time = $value;
    }

    public function getAccessAnyTimeAttribute()
    {
        return $this->_access_any_time;
    }

    public function getWapIdAttribute()
    {
        return $this->wap ? $this->wap->id : null;
    }

    public function getWapStatusAttribute()
    {
        return $this->wap ? $this->wap->status : null;
    }

    public function getWapTypeAttribute()
    {
        return $this->wap ? $this->wap->type : null;
    }

    public function getHasSignupsAttribute()
    {
        return $this->has_signups;
    }

    public function isPrintable()
    {
        if (!$this->person || !in_array($this->person->status, self::ALLOWED_PERSON_STATUSES)) {
            return false;
        }

        if (!in_array($this->status, self::READY_TO_PRINT_STATUSES)) {
            return false;
        }

        return true;
    }
}
