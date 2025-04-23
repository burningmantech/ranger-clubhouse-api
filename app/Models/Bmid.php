<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
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


    protected ?AccessDocument $wap = null;

    protected bool $access_any_time = false;
    protected ?string $access_date = null;

    protected bool $has_signups = false;
    protected bool $org_vehicle_insurance = false;

    protected bool $has_ticket = false;
    protected bool $training_signed_up = false;

    protected array $meals_granted = [];
    protected bool $showers_granted = false;
    protected bool $have_allocated_provisions = false;

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
        'pre_event_meals',
        'event_week_meals',

        // pseudo-columns
        'access_date',
        'access_any_time',
    ];

    protected $virtualColumns = [
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

    protected $appends = [
        'access_any_time',
        'access_date',
        'has_approved_photo',
        'has_signups',
        'has_ticket',
        'have_allocated_provisions',
        'meals_granted',
        'org_vehicle_insurance',
        'showers_granted',
        'training_signed_up',
        'wap_id',
        'wap_status',
        'wap_type',
    ];

    protected function casts(): array
    {
        return [
            'access_any_time' => 'bool',
            'access_date' => 'datetime:Y-m-d',
            'created_at' => 'datetime',
            'has_signups' => 'bool',
            'have_allocated_provisions' => 'bool',
            'org_vehicle_insurance' => 'bool',
            'showers_granted' => 'bool',
            'updated_at' => 'datetime',
        ];
    }

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

    public function loadRelationships(): void
    {
        self::bulkLoadRelationships(new EloquentCollection([$this]), [$this->person_id]);
    }

    public function setWap($wap): void
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
        $sql = Bmid::where('year', $year)->whereIntegerInRaw('person_id', $personIds);
        if ($excludeDeparted) {
            $sql->select('bmid.*')
                ->join('person', 'person.id', '=', 'bmid.person_id')
                ->whereNotIn('person.status', [Person::DECEASED, Person::DISMISSED, Person::RESIGNED]);
        }

        $bmids = $sql->get();
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

        return $bmids->sortBy(fn($bmid, $key) => $bmid->person->callsign, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Populate BMIDs with access date, provisions, approved photo check, and job provisions
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
            'person.person_photo:id,person_id,status',
        ]);

        // Paranoia check - filter out any deleted accounts
        $bmids = $bmids->filter(fn($bmid) => $bmid->person != null);

        // Load up the org insurance flags
        $personEvents = PersonEvent::findAllForIdsYear($personIds, $year)->keyBy('person_id');
        foreach ($bmids as $bmid) {
            $event = $personEvents->get($bmid->person_id);
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

        $provisionsByPerson = Provision::retrieveUsableForPersonIds($personIds)->groupBy('person_id');

        foreach ($personIds as $id) {
            $cookies = $provisionsByPerson->get($id);
            $package = Provision::buildPackage($cookies ?? []);
            $bmid = $bmidsByPerson[$id];
            $bmid->meals_granted = $package['meals'];
            $bmid->showers_granted = $package['showers'];
            $bmid->have_allocated_provisions = $package['have_allocated'];
        }

        $trainingIds = null;
        if (!empty($slotIds)) {
            $trainingIds = DB::table('person_slot')
                ->whereIntegerInRaw('person_id', $personIds)
                ->whereIntegerInRaw('slot_id', $slotIds)
                ->get()
                ->groupBy('person_id');
        }

        foreach ($bmids as $bmid) {
            // Don't send back the photo info.
            $bmid->person->makeHidden(['person_photo_id','person_photo']);
            $bmid->training_signed_up = $trainingIds?->has($bmid->person_id) ?? false;
            $bmid->has_ticket = $ticketIds->has($bmid->person_id);
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
        AccessDocument::updateSAPsForPerson($this->person_id, $this->access_date, $this->access_any_time, 'set via BMID update');

        $wap = $this->wap;
        if ($wap) {
            $wap->refresh();
            $this->setWap($wap);
        }
    }

    public function title1(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function title2(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function title3(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function team(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }


    public function accessDate(): Attribute
    {
        return Attribute::make(
            get: fn() => !empty($this->access_date) ? (string)$this->access_date : null,
            set: fn($value) => $this->access_date = $value
        );
    }

    public function accessAnyTime(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->access_any_time,
            set: fn($value) => $this->access_any_time = $value
        );
    }

    public function orgVehicleInsurance(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->org_vehicle_insurance,
            set: fn($value) => $this->org_vehicle_insurance = $value
        );
    }

    public function wapId(): Attribute
    {
        return Attribute::make(get: fn() => $this->wap?->id);
    }

    public function wapStatus(): Attribute
    {
        return Attribute::make(get: fn() => $this->wap?->status);
    }

    public function wapType(): Attribute
    {
        return Attribute::make(get: fn() => $this->wap?->type);
    }

    public function hasSignups(): Attribute
    {
        return Attribute::make(get: fn() => $this->has_signups);
    }

    public function hasApprovedPhoto(): Attribute
    {
        return Attribute::make(get: fn() => $this->person->hasApprovedPhoto());
    }

    public function hasTicket(): Attribute
    {
        return Attribute::make(get: fn(): bool => $this->has_ticket);
    }

    public function trainingSignedUp(): Attribute
    {
        return Attribute::make(get: fn(): bool => $this->training_signed_up);
    }

    public function mealsGranted(): Attribute
    {
        return Attribute::make(get: fn() => $this->meals_granted);
    }

    public function showersGranted(): Attribute
    {
        return Attribute::make(get: fn() => $this->showers_granted);
    }

    public function haveAllocatedProvisions(): Attribute
    {
        return Attribute::make(get: fn() => $this->have_allocated_provisions);
    }

    /**
     * Is the BMID printable (both person & BMID have to be an acceptable status)
     *
     * @return bool
     */

    public function isPrintable(): bool
    {
        if (!in_array($this->person->status, self::ALLOWED_PERSON_STATUSES)) {
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

    public function appendNotes(string $notes): void
    {
        $date = date('n/j/y G:i:s');
        $callsign = Auth::check() ? Auth::user()->callsign : '(unknown)';
        $this->notes = "$date $callsign: $notes\n{$this->notes}";
    }


}
