<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\Position;
use App\Models\PersonEvent;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Bmid extends ApiModel
{
    protected $table = 'bmid';
    protected $auditModel = true;

    const MEALS_ALL = 'all';
    const MEALS_EVENT = 'event';
    const MEALS_EVENT_PLUS_POST = 'event+post';
    const MEALS_POST = 'post';
    const MEALS_PRE = 'pre';
    const MEALS_PRE_PLUS_EVENT = 'pre+event';
    const MEALS_PRE_PLUS_POST = 'pre+post';

    const MEALS_TYPES = [
        self::MEALS_ALL,
        self::MEALS_EVENT,
        self::MEALS_EVENT_PLUS_POST,
        self::MEALS_POST,
        self::MEALS_PRE,
        self::MEALS_PRE_PLUS_EVENT,
        self::MEALS_PRE_PLUS_POST
    ];

    // BMID is being prepped
    const IN_PREP = 'in_prep';
    // Ready to be sent off to be printed
    const READY_TO_PRINT = 'ready_to_print';
    // BMID was changed (name, photos, titles, etc.) and needs to be reprinted
    const READY_TO_REPRINT_CHANGE = 'ready_to_reprint_changed';
    // BMID was lost and a new one issued
    const READY_TO_REPRINT_LOST = 'ready_to_reprint_lost';

    // BMID has issues, do not print.
    const ISSUES = 'issues';

    // Person is not rangering this year (common) or another reason.
    const DO_NOT_PRINT = 'do_not_print';

    const READY_TO_PRINT_STATUSES = [
        self::IN_PREP,
        self::READY_TO_PRINT,
        self::READY_TO_REPRINT_CHANGE,
        self::READY_TO_REPRINT_LOST,
    ];

    const ALLOWED_PERSON_STATUSES = [
        Person::ACTIVE,
        Person::INACTIVE,
        Person::INACTIVE_EXTENSION,
        Person::RETIRED,
        Person::ALPHA,
        Person::PROSPECTIVE
    ];

    const PERSON_WITH = 'person:id,callsign,status,first_name,last_name,email,bpguid';

    protected $wap;

    protected $access_any_time = false;
    protected $access_date = null;

    protected $uploadedToLambase = false;
    protected $has_signups = false;
    protected $org_vehicle_insurance = false;

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
        'create_datetime',
        'modified_datetime'
    ];

    protected $attributes = [
        'showers' => false,
        'meals' => null,
    ];

    protected $casts = [
        'showers' => 'bool',
        'org_vehicle_insurance' => 'bool',
        'create_datetime' => 'datetime',
        'modified_datetime' => 'datetime',
        'access_date' => 'datetime:Y-m-d',
        'access_any_time' => 'bool',
    ];

    protected $appends = [
        'access_any_time',
        'access_date',
        'has_signups',
        'org_vehicle_insurance',
        'wap_id',
        'wap_status',
        'wap_type',
    ];

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (empty($model->status)) {
                $model->status = self::IN_PREP;
            }
        });

        self::saved(function ($model) {
            $model->updateWap();
            $model->syncToAccessDocuments();
        });

        self::created(function ($model) {
            $model->updateWap();
            $model->syncToAccessDocuments();
        });
    }

    public function person()
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

        $bmids = $bmids->sortBy(function ($bmid, $key) {
            return $bmid->person ? $bmid->person->callsign : "";
        }, SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $bmids;
    }

    public static function bulkLoadRelationships($bmids, $personIds)
    {
        $year = current_year();

        // Populate all the BMIDs with people..
        $bmids->load([self::PERSON_WITH]);

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

        $newBmids = $bmids->filter(fn($b) => !$b->id);
        if ($newBmids->isEmpty()) {
            // no new BMIDs to process
            return;
        }


        $itemsByPersonId = AccessDocument::whereIn('person_id', $newBmids->pluck('person_id'))
            ->where('status', AccessDocument::CLAIMED)
            ->whereIn('type', [AccessDocument::ALL_YOU_CAN_EAT, AccessDocument::WET_SPOT])
            ->get()
            ->groupBy('person_id');

        foreach ($bmids as $bmid) {
            $items = $itemsByPersonId->get($bmid->person_id);
            if (!$items) {
                continue;
            }

            $meals = $items->firstWhere('type', AccessDocument::ALL_YOU_CAN_EAT);
            if ($meals) {
                $bmid->meals = self::MEALS_ALL;
            }

            $showers = $items->firstWhere('type', AccessDocument::WET_SPOT);
            if ($showers) {
                $bmid->showers = true;
            }
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

        $year = $query['year'] ?? null;
        if ($year) {
            $sql->where('year', $year);
        }

        $bmids = $sql->with(['person:id,callsign,email'])->get();

        self::bulkLoadRelationships($bmids, $bmids->pluck('person_id')->toArray());

        return $bmids;
    }


    public function updateWap()
    {
        AccessDocument::updateWAPsForPerson($this->person_id, $this->access_date, $this->access_any_time, 'set via BMID update');

        $wap = $this->wap;
        if ($wap) {
            $wap->refresh();
            $this->setWap($wap);
        }
    }

    /**
     * Sync the BMID appreciations choices against the Person Items.
     */

    public function syncToAccessDocuments()
    {
        $items = AccessDocument::where('person_id', $this->person_id)
            ->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::BANKED])
            ->whereIn('type', [AccessDocument::ALL_YOU_CAN_EAT, AccessDocument::WET_SPOT])
            ->get();

        $this->syncItem($items, AccessDocument::ALL_YOU_CAN_EAT, $this->meals == self::MEALS_ALL);
        $this->syncItem($items, AccessDocument::WET_SPOT, $this->showers);
    }

    public function syncItem($items, $type, $claim)
    {
        $status = $claim ? AccessDocument::CLAIMED : AccessDocument::BANKED;
        $item = $items->firstWhere('type', $type);
        if ($item && $item->status != $status) {
            $item->status = $status;
            $item->auditReason = $item->additional_comments = "{$status} via BMID";
            $item->saveWithoutValidation();
        }
    }

    public static function syncFromAccessDocument(AccessDocument $ad)
    {
        if (!in_array($ad->type, [AccessDocument::ALL_YOU_CAN_EAT, AccessDocument::WET_SPOT])) {
            return;
        }

        if (!in_array($ad->status, [AccessDocument::CLAIMED, AccessDocument::BANKED])) {
            return;
        }

        $bmid = self::findForPersonYear($ad->person_id, current_year());
        if (!$bmid) {
            return;
        }

        $bmid->loadRelationships();

        $status = $ad->status;
        $reason = null;
        switch ($ad->type) {
            case AccessDocument::ALL_YOU_CAN_EAT:
                // Person wants the buffet!
                if ($status == AccessDocument::CLAIMED) {
                    if ($bmid->meals == self::MEALS_ALL) {
                        // Already set for all you can eat.
                        return;
                    }
                    $bmid->meals = self::MEALS_ALL;
                    $reason = 'claimed all-you-can-eat';
                } else if ($status == AccessDocument::BANKED) {
                    if (empty($bmid->meals)) {
                        return; // nothing to do.
                    }
                    $bmid->meals = '';
                    $reason = 'banked all-you-can-eat';
                }
                break;
            case AccessDocument::WET_SPOT:
                // Person wants to be clean.
                if ($status == AccessDocument::CLAIMED) {
                    if ($bmid->showers) {
                        // Already set for showers
                        return;
                    }
                    $bmid->showers = true;
                    $reason = 'claimed wet-spot';
                } else if ($status == AccessDocument::BANKED) {
                    if (!$bmid->showers) {
                        return; // already banked.
                    }
                    $bmid->showers = false;
                    $reason = 'banked wet-spot';
                }
                break;
        }

        if (!$reason) {
            return; // nothing happened.
        }

        $bmid->auditReason = $reason;
        $bmid->appendNotes($reason);
        $bmid->saveWithoutValidation();
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

    public function setOrgVehicleInsuranceAttribute($value) {
        $this->org_vehicle_insurance = $value;
    }

    public function getOrgVehicleInsuranceAttribute() {
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

    public function getHasSignupsAttribute()
    {
        return $this->has_signups;
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
}
