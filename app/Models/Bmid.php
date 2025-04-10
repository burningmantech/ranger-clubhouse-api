<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Bmid extends ApiModel
{
    protected $table = 'bmid';
    protected bool $auditModel = true;
    public $timestamps = true;

    const string MEALS_ALL = 'all';
    const string MEALS_EVENT = 'event';
    const string MEALS_EVENT_PLUS_POST = 'event+post';
    const string MEALS_POST = 'post';
    const string MEALS_PRE = 'pre';
    const string MEALS_PRE_PLUS_EVENT = 'pre+event';
    const string MEALS_PRE_PLUS_POST = 'pre+post';

    const array MEALS_TYPES = [
        self::MEALS_ALL,
        self::MEALS_EVENT,
        self::MEALS_EVENT_PLUS_POST,
        self::MEALS_POST,
        self::MEALS_PRE,
        self::MEALS_PRE_PLUS_EVENT,
        self::MEALS_PRE_PLUS_POST
    ];

    // BMID is being prepped
    const string IN_PREP = 'in_prep';
    // Ready to be sent off to be printed
    const string READY_TO_PRINT = 'ready_to_print';
    // BMID was changed (name, photos, titles, etc.) and needs to be reprinted
    const string READY_TO_REPRINT_CHANGE = 'ready_to_reprint_changed';
    // BMID was lost and a new one issued
    const string READY_TO_REPRINT_LOST = 'ready_to_reprint_lost';

    // BMID has issues, do not print.
    const string ISSUES = 'issues';

    // Person is not rangering this year (common) or another reason.
    const string DO_NOT_PRINT = 'do_not_print';

    // BMID was submitted
    const string SUBMITTED = 'submitted';

    const array READY_TO_PRINT_STATUSES = [
        self::IN_PREP,
        self::READY_TO_PRINT,
        self::READY_TO_REPRINT_CHANGE,
        self::READY_TO_REPRINT_LOST,
    ];

    const array ALLOWED_PERSON_STATUSES = [
        Person::ACTIVE,
        Person::ALPHA,
        Person::INACTIVE,
        Person::INACTIVE_EXTENSION,
        Person::ECHELON,
        Person::PROSPECTIVE,
        Person::RETIRED,
    ];

    const array BADGE_TITLES = [
        // Title 1
        Position::RSC_SHIFT_LEAD => ['title1', 'Shift Lead'],
        Position::DEPARTMENT_MANAGER => ['title1', 'Department Manager'],
        Position::OPERATIONS_MANAGER => ['title1', 'Operations Manager'],
        Position::OOD => ['title1', 'Officer of the Day'],
        // Title 2
        // Position::TROUBLESHOOTER_LEAL => ['title2', 'LEAL'],
        // Title 3
        Position::DOUBLE_OH_7 => ['title3', '007']
    ];


    protected $wap;

    protected $access_any_time = false;
    protected $access_date = null;

    protected $has_signups = false;
    protected $org_vehicle_insurance = false;

    protected $allocated_meals = '';
    protected $earned_meals = '';

    protected bool $allocated_showers = false;
    protected bool $earned_showers = false;

    protected bool $has_approved_photo = false;

    protected bool $has_ticket = false;
    protected bool $training_signed_up = false;

    protected $fillable = [
        'person_id',
        'year',
        'status',
        'title1',
        'title2',
        'title3',
        'team',
        'showers',
        'meals',
        'batch',
        'notes',

        // pseudo-columns
        'access_date',
        'access_any_time',
    ];

    protected $guarded = [
        'created_at',
        'updated_at'
    ];

    protected $attributes = [
        'showers' => false,
        'meals' => null,
    ];

    protected function casts(): array
    {
        return [
            'access_any_time' => 'bool',
            'access_date' => 'datetime:Y-m-d',
            'allocated_showers' => 'bool',
            'created_at' => 'datetime',
            'earned_showers' => 'bool',
            'org_vehicle_insurance' => 'bool',
            'showers' => 'bool',
            'updated_at' => 'datetime',
        ];
    }

    protected $appends = [
        'access_any_time',
        'access_date',
        'allocated_meals',
        'allocated_showers',
        'earned_meals',
        'earned_showers',
        'has_approved_photo',
        'has_signups',
        'has_ticket',
        'org_vehicle_insurance',
        'training_signed_up',
        'wap_id',
        'wap_status',
        'wap_type',
    ];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = self::IN_PREP;
            }
        });

        self::saved(function ($model) {
            $model->updateWap();
        });

        self::created(function ($model) {
            $model->updateWap();
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
        self::bulkLoadRelationships(new EloquentCollection([$this]), [$this->person_id]);
    }

    public function setWap($wap)
    {
        $this->access_date = $wap->access_date;
        $this->access_any_time = $wap->access_any_time;
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

    public static function findForPersonYear($personId, $year): ?Bmid
    {
        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    public static function findForPersonManage($personId, $year)
    {
        $rows = self::findForPersonIds($year, [$personId]);
        return $rows[0];
    }

    public static function findForPersonIds($year, $personIds, $excludeDeparted = false)
    {
        if (empty($personIds)) {
            return [];
        }

        // Bulk look up
        $bmids = Bmid::where('year', $year)->whereIntegerInRaw('person_id', $personIds)->get();
        $bmidsByPerson = $bmids->keyBy('person_id');

        // Figure out which people do not have BMIDs yet.
        foreach ($personIds as $personId) {
            if (!$bmidsByPerson->has($personId)) {
                $bmid = new Bmid([
                    'person_id' => $personId,
                    'year' => $year,
                    'status' => self::IN_PREP
                ]);

                $bmids->push($bmid);
                $bmidsByPerson[$personId] = $bmid;
            }
        }

        self::bulkLoadRelationships($bmids, $personIds);

        if ($excludeDeparted) {
            $bmids = $bmids->whereNotIn('person.status', [Person::DECEASED, Person::DISMISSED, Person::RESIGNED]);
        }

        $bmids = $bmids->sortBy(
            fn($bmid, $key) => ($bmid->person ? $bmid->person->callsign : ""),
            SORT_NATURAL | SORT_FLAG_CASE
        )->values();

        return $bmids;
    }

    /**
     * Populate BMIDs with access date, desired provisions, approved photo check, and job provisions
     *
     * @param $bmids
     * @param $personIds
     * @return void
     */

    public static function bulkLoadRelationships($bmids, $personIds): void
    {
        $year = current_year();

        // Populate all the BMIDs with people..
        $bmids->load([
            'person:id,callsign,status,first_name,last_name,email,bpguid,person_photo_id',
            'person.person_photo:id,status'
        ]);

        // Load up the org insurance flags
        $personEvents = PersonEvent::findAllForIdsYear($personIds, $year)->keyBy('person_id');
        foreach ($bmids as $bmid) {
            $event = $personEvents->get($bmid->person_id);
            $bmid->has_approved_photo = $bmid->person?->person_photo?->isApproved() ?? false;
            if ($event) {
                $bmid->org_vehicle_insurance = $event->org_vehicle_insurance;
            }
        }

        // Set the WAPs
        $waps = AccessDocument::findWAPForPersonIds($personIds);
        $bmidsByPerson = $bmids->keyBy('person_id');
        foreach ($waps as $personId => $wap) {
            $bmidsByPerson[$personId]->setWap($wap);
        }

        $ids = DB::table('person')
            ->select('id')
            ->whereIntegerInRaw('id', $personIds)
            ->whereRaw("EXISTS (SELECT 1 FROM person_slot JOIN slot ON person_slot.slot_id=slot.id WHERE person.id=person_slot.person_id AND slot.begins_year=$year LIMIT 1)")
            ->get()
            ->pluck('id');

        foreach ($ids as $id) {
            $bmidsByPerson[$id]->has_signups = true;
        }


        // The provisions are special - by default, the items are opt-out so treat an item qualified as
        // the same as claimed.

        $itemsByPersonId = Provision::whereIntegerInRaw('person_id', $bmids->pluck('person_id'))
            ->whereIn('status', [Provision::AVAILABLE, Provision::CLAIMED, Provision::SUBMITTED])
            ->whereIn('type', [...Provision::MEAL_TYPES, Provision::WET_SPOT])
            ->get()
            ->groupBy('person_id');

        $ticketIds = DB::table('access_document')
            ->whereIntegerInRaw('person_id', $personIds)
            ->whereIn('type', [AccessDocument::SPT, AccessDocument::STAFF_CREDENTIAL, AccessDocument::WAP])
            ->whereIn('status', [AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->get()
            ->groupBy('person_id');

        $slotIds = DB::table('slot')
            ->where('begins_year', $year)
            ->whereIn('position_id', [Position::TRAINING, Position::TRAINER, Position::TRAINER_ASSOCIATE, Position::TRAINER_UBER])
            ->where('active', true)
            ->pluck('id')
            ->toArray();

        if (!empty($slotIds)) {
            $trainingIds = DB::table('person_slot')
                ->whereIntegerInRaw('person_id', $personIds)
                ->whereIntegerInRaw('slot_id', $slotIds)
                ->get()
                ->groupBy('person_id');
        } else {
            $trainingIds = collect([]);
        }

        foreach ($bmids as $bmid) {
            $bmid->training_signed_up = $trainingIds->has($bmid->person_id);
            $bmid->has_ticket = $ticketIds->has($bmid->person_id);

            $items = $itemsByPersonId->get($bmid->person_id);
            if (!$items) {
                continue;
            }

            $allocatedMeals = [];
            $earnedMeals = [];

            foreach ($items as $item) {
                $isMeal = in_array($item->type, Provision::MEAL_TYPES);
                if ($isMeal) {
                    if ($item->is_allocated) {
                        self::populateMealMatrix(Provision::MEAL_MATRIX[$item->type], $allocatedMeals);
                    } else {
                        self::populateMealMatrix(Provision::MEAL_MATRIX[$item->type], $earnedMeals);
                    }
                }
                if ($item->type == Provision::WET_SPOT) {
                    if ($item->is_allocated) {
                        $bmid->allocated_showers = true;
                    } else {
                        $bmid->earned_showers = true;
                    }
                }
            }

            if (!empty($allocatedMeals)) {
                $bmid->allocated_meals = self::sortMeals($allocatedMeals);
            }

            if (!empty($earnedMeals)) {
                $bmid->earned_meals = self::sortMeals($earnedMeals);
            }
        }
    }

    /**
     * For a given person and year, find an existing record or create a new one.
     *
     * @param $personId
     * @param $year
     * @return Bmid
     */

    public static function firstOrNewForPersonYear($personId, $year): Bmid
    {
        $row = self::firstOrNew(['person_id' => $personId, 'year' => $year]);
        $row->loadRelationships();

        return $row;
    }

    /**
     * Find access documents matching the criteria.
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {
        $sql = self::query();

        $year = $query['year'] ?? null;
        if ($year) {
            $sql->where('year', $year);
        }

        $bmids = $sql->with(['person:id,callsign,email'])->get();

        self::bulkLoadRelationships($bmids, $bmids->pluck('person_id')->toArray());

        return $bmids;
    }

    /**
     * Update any access documents with new access date
     *
     * @return void
     */

    public function updateWap(): void
    {
        AccessDocument::updateWAPsForPerson($this->person_id, $this->access_date, $this->access_any_time, 'set via BMID update');

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
        $this->access_date = $value;
    }

    public function getAccessDateAttribute()
    {
        return (string)$this->access_date;
    }

    public function setAccessAnyTimeAttribute($value)
    {
        $this->access_any_time = $value;
    }

    public function getAccessAnyTimeAttribute()
    {
        return $this->access_any_time;
    }

    public function setOrgVehicleInsuranceAttribute($value)
    {
        $this->org_vehicle_insurance = $value;
    }

    public function getOrgVehicleInsuranceAttribute()
    {
        return $this->org_vehicle_insurance;
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

    public function getHasSignupsAttribute(): bool
    {
        return $this->has_signups;
    }

    public function getEarnedMealsAttribute()
    {
        return $this->earned_meals;
    }

    public function getAllocatedMealsAttribute()
    {
        return $this->allocated_meals;
    }

    public function getEarnedShowersAttribute(): bool
    {
        return $this->earned_showers;
    }

    public function getAllocatedShowersAttribute(): bool
    {
        return $this->allocated_showers;
    }

    public function getHasApprovedPhotoAttribute(): bool
    {
        return $this->has_approved_photo;
    }

    public function getHasTicketAttribute(): bool
    {
        return $this->has_ticket;
    }

    public function getTrainingSignedUpAttribute(): bool
    {
        return $this->training_signed_up;
    }

    /**
     * Is the BMID printable (both person & BMID have to be an acceptable status)
     *
     * @return bool
     */

    public function isPrintable(): bool
    {
        if (!$this->person || !in_array($this->person->status, self::ALLOWED_PERSON_STATUSES)) {
            return false;
        }

        if (!in_array($this->status, self::READY_TO_PRINT_STATUSES)) {
            return false;
        }

        return true;
    }

    /**
     * Append to the notes with timestamp and callsign.
     *
     * @param string $notes
     */

    public function appendNotes(string $notes)
    {
        $date = date('n/j/y G:i:s');
        $callsign = Auth::check() ? Auth::user()->callsign : '(unknown)';
        $this->notes = "$date $callsign: $notes\n{$this->notes}";
    }

    /**
     * Builds a "meal matrix" indicating which weeks the person can
     * have meals. This is a union between what has been set by the
     * BMID administrator, the allocated provisions, and claimed earned provisions.
     *
     * @return array
     */

    public function buildMealsMatrix(): array
    {
        if ($this->meals == self::MEALS_ALL
            || $this->earned_meals == self::MEALS_ALL
            || $this->allocated_meals == self::MEALS_ALL) {
            return [self::MEALS_PRE => true, self::MEALS_EVENT => true, self::MEALS_POST => true];
        }

        $matrix = [];
        self::populateMealMatrix($this->meals, $matrix);
        self::populateMealMatrix($this->earned_meals, $matrix);
        self::populateMealMatrix($this->allocated_meals, $matrix);

        return $matrix;
    }

    public static function populateMealMatrix($meals, &$matrix)
    {
        if (empty($meals)) {
            return;
        }

        foreach (explode('+', $meals) as $week) {
            $matrix[$week] = true;
        }
    }

    public function effectiveMeals(): string
    {
        return self::sortMeals($this->buildMealsMatrix());
    }

    public function effectiveShowers(): bool
    {
        return $this->showers || $this->allocated_showers || $this->earned_showers;
    }

    /**
     * Sort the meals into the expected order: pre-event, event, and post-event weeks.
     *
     * @param $meals
     * @return string
     */

    public static function sortMeals($meals): string
    {
        if (count($meals) == 3) {
            return self::MEALS_ALL;
        }

        $sorted = [];
        if ($meals[self::MEALS_PRE] ?? null) {
            $sorted[] = self::MEALS_PRE;
        }
        if ($meals[self::MEALS_EVENT] ?? null) {
            $sorted[] = self::MEALS_EVENT;
        }

        if ($meals[self::MEALS_POST] ?? null) {
            $sorted[] = self::MEALS_POST;
        }

        return implode('+', $sorted);
    }
}
