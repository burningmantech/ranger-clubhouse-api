<?php

namespace App\Models;

use App\Models\ApiModel;
use App\Models\Person;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class Vehicle extends ApiModel
{
    protected $table = 'vehicle';
    public $timestamps = true;

    protected $auditModel = true;

    // Request status
    const PENDING = 'pending';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';

    // Driving stickers
    const DRIVING_STICKER_NONE = 'none';
    const DRIVING_STICKER_PREPOST = 'prepost';
    const DRIVING_STICKER_STAFF = 'staff';

    // Fuel chit - gimme some gas man!
    const FUEL_CHIT_EVENT = 'event';
    const FUEL_CHIT_SINGLE_USE = 'single-use';
    const FUEL_CHIT_NONE = 'none';

    // Ranger logo decals
    const LOGO_PERMANENT_NEW = 'permanent-new';
    const LOGO_PERMANENT_EXISTING = 'permanent-existing';
    const LOGO_EVENT = 'event';
    const LOGO_NONE = 'none';

    // Ranger wants blinky lights
    const AMBER_LIGHT_NONE = 'none';
    const AMBER_LIGHT_DEPARTMENT = 'department';
    const AMBER_LIGHT_ALREADY_HAS = 'already-has';

    // Vehicle type
    const FLEET = 'fleet';
    const PERSONAL = 'personal';

    protected $fillable = [
        'type',
        'callsign',  //pseudo field
        'person_id',
        'status',
        'event_year',
        'team_assignment',
        'rental_number',
        'vehicle_class',
        'vehicle_type',
        'vehicle_year',
        'vehicle_make',
        'vehicle_model',
        'vehicle_color',
        'vehicle_type',
        'license_number',
        'license_state',
        'driving_sticker',
        'sticker_number',
        'fuel_chit',
        'ranger_logo',
        'amber_light',
        'notes',
        'response',
        'request_note',
        'request_comment'
    ];

    protected $appends = [
        'org_vehicle_insurance',
        'signed_motorpool_agreement'
    ];

    protected $rules = [
        'type' => 'required|string',
        'person_id' => 'required_if:type,personal|nullable|integer',
        'team_assignment' => 'required_if:type,fleet|nullable|string',
        'event_year' => 'required|integer',
        'vehicle_year' => 'sometimes|string', // may be an integer or string (e.g., 2019 or TBD)
        'license_number' => 'sometimes|string',
        'license_state' => 'sometimes|string',
        'vehicle_color' => 'required|string',
        'vehicle_make' => 'required|string',
        'vehicle_model' => 'required|string'
    ];

    protected $attributes = [
        'amber_light' => self::AMBER_LIGHT_NONE,
        'driving_sticker' => self::DRIVING_STICKER_NONE,
        'fuel_chit' => self::FUEL_CHIT_NONE,
        'notes' => '',
        'ranger_logo' => self::LOGO_NONE,
        'request_comment' => '',
        'response' => '',
        'status' => self::PENDING,
        'sticker_number' => '',
        'team_assignment' => '',
        'type' => 'personal',
    ];

    public $callsign;

    public static function boot()
    {
        parent::boot();

        self::saving(function ($model) {
            // Always force a fleet vehicle to be approved. status is only for personal vehicles
            if ($model->type == self::FLEET) {
                $model->status = self::APPROVED;
            }
        });
    }

    /**
     * Find all vehicles for the given criteria
     *
     * @param array $query
     * @return Collection|Vehicle[]
     */

    public static function findForQuery(array $query)
    {
        $personId = $query['person_id'] ?? null;
        $eventYear = $query['event_year'] ?? null;
        $license = $query['license_number'] ?? null;
        $sticker = $query['sticker_number'] ?? null;
        $status = $query['status'] ?? null;
        $number = $query['number'] ?? null;

        $sql = self::select(
            'vehicle.*',
            DB::raw('IFNULL(person_event.org_vehicle_insurance,false) AS org_vehicle_insurance'),
            DB::raw('IFNULL(person_event.signed_motorpool_agreement,false) AS signed_motorpool_agreement')
        )->leftJoin('person_event', function ($j) {
            $j->on('person_event.year', 'vehicle.event_year');
            $j->on('person_event.person_id', 'vehicle.person_id');
        })->orderBy('event_year')->with('person:id,callsign');

        if ($personId) {
            $sql->where('vehicle.person_id', $personId);
        }

        if ($eventYear) {
            $sql->where('vehicle.event_year', $eventYear);
        }

        if ($status) {
            $sql->where('vehicle.status', $status);
        }

        if ($license) {
            $sql->where('vehicle.license_number', 'LIKE', '%' . preg_replace('/[\s\-]/', '', $license) . '%');
        }

        if ($sticker) {
            $sql->where('vehicle.sticker_number', 'LIKE', '%' . $sticker . '%');
        }

        if ($number) {
            $sql->where(function ($q) use ($number) {
                $q->where('vehicle.license_number', 'LIKE', '%' . preg_replace('/[\s\-]/', '', $number) . '%');
                $q->orWhere('vehicle.sticker_number', 'LIKE', '%' . $number . '%');
                $q->orWhere('vehicle.rental_number', 'LIKE', '%' . $number . '%');
            });
        }


        return $sql->get();
    }

    /**
     * Find all the vehicles for a person and event year
     *
     * @param int $personId
     * @param int $year Event year to look in
     * @return Vehicle[]|Collection
     */
    public static function findForPersonYear(int $personId, int $year)
    {
        return self::select(
            'vehicle.*',
            DB::raw('IFNULL(person_event.org_vehicle_insurance,false) AS org_vehicle_insurance'),
            DB::raw('IFNULL(person_event.signed_motorpool_agreement,false) AS signed_motorpool_agreement')
        )->leftJoin('person_event', function ($j) {
            $j->on('person_event.year', 'vehicle.event_year');
            $j->on('person_event.person_id', 'vehicle.person_id');
        })->where('vehicle.person_id', $personId)
            ->where('event_year', $year)
            ->orderBy('license_number')
            ->get();
    }

    /**
     * Find all personal vehicle requests queued for review in the current year
     *
     * @return Vehicle[]|Collection
     */

    public static function findAllPending() {
        return self::where('status', self::PENDING)
                ->where('type', self::PERSONAL)
                ->where('event_year', current_year())
                ->with([ 'person:id,callsign'])
                ->get()
                ->sortBy('person.callsign')
                ->values();
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function loadRelationships()
    {
        $this->load('person:id,callsign');
        $pv = PersonEvent::findForPersonYear($this->person_id, $this->event_year);
        if ($pv) {
            $this->signed_motorpool_agreement = $pv->signed_motorpool_agreement;
            $this->org_vehicle_insurance = $pv->org_vehicle_insurance;
        } else {
            $this->signed_motorpool_agreement = false;
            $this->org_vehicle_insurance = false;
        }
    }

    /**
     * Save a person vehicle record. If $this->callsign is set,
     * verify the callsign and set person_id
     *
     * @param array $options
     * @return bool
     */

    public function save($options = [])
    {
        if (!empty($this->callsign)) {
            // Find the callsign
            $this->person_id = Person::findIdByCallsign($this->callsign);
            if (!$this->person_id) {
                // callsign not found.. punt
                $this->addError('callsign', 'Callsign not found.');
                return false;
            }
        } else if (empty($this->person_id) && $this->type == self::PERSONAL) {
            $this->addError('callsign', 'Missing callsign.');
            return false;
        }


        if ($this->isDirty('license_state') || $this->isDirty('license_number')) {
            $checkSql = self::where([
                'license_state' => $this->license_state,
                'license_number' => $this->license_number,
                'event_year' => $this->event_year
            ]);

            if ($this->exists()) {
                $checkSql->where('id', '!=', $this->id);
            }

            if ($checkSql->exists()) {
                $this->addError('license_number', 'Another vehicle already exists with the same license plate.');
                return false;
            }
        }

        if ($this->isDirty('rental_number') && !empty($this->rental_number)) {
            $checkSql = self::where([
                'rental_number' => $this->rental_number,
                'event_year' => $this->event_year
            ]);

            if ($this->exists()) {
                $checkSql->where('id', '!=', $this->id);
            }

            if ($checkSql->exists()) {
                $this->addError('rental_number', 'Another vehicle already exists with the same rental number.');
                return false;
            }
        }

        return parent::save($options);
    }

    public function setCallsignAttribute($value)
    {
        $this->callsign = $value;
    }

    public function setLicenseNumberAttribute($value)
    {
        $this->attributes['license_number'] = preg_replace('/[\s\-]/', '', $value);
    }

    public function setNotesAttribute($value)
    {
        $this->attributes['notes'] = $value ?? '';
    }

    public function setVehicleClassAtrribute($value)
    {
        $this->attributes['vehicle_class'] = $value ?? '';
    }

    public function setRentalNumberAttribute($value)
    {
        $this->attributes['rental_number'] = $value ?? '';
    }

    public function setPrepostStickerAttribute($value)
    {
        $this->attributes['prepost_sticker'] = $value ?? '';
    }

    public function setVehicleYearAttribute($value)
    {
        $this->attributes['vehicle_year'] = $value ?? '';
    }

    public function setTeamAssignmentAttribute($value)
    {
        $this->attributes['team_assignment'] = $value ?? '';
    }

    public function setDrivingStickerAttribute($value)
    {
        $this->attributes['driving_sticker'] = $value ?? '';
    }

    public function setRequestCommentAttribute($value)
    {
        $this->attributes['request_comment'] = $value ?? '';
    }


    public function getOrgVehicleInsuranceAttribute()
    {
        return $this->attributes['org_vehicle_insurance'] ?? false;
    }

    public function getSignedMotorpoolAgreementAttribute()
    {
        return $this->attributes['signed_motorpool_agreement'] ?? false;
    }
}
