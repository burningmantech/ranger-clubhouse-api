<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class AccessDocument extends ApiModel
{
    protected $table = 'access_document';
    protected $auditModel = true;

    // Statuses
    const QUALIFIED = 'qualified';
    const CLAIMED = 'claimed';
    const BANKED = 'banked';
    const USED = 'used';
    const CANCELLED = 'cancelled';
    const EXPIRED = 'expired';
    const SUBMITTED = 'submitted';

    const ACTIVE_STATUSES = [
        self::QUALIFIED,
        self::CLAIMED,
        self::BANKED
    ];

    const CHECK_STATUSES = [
        self::QUALIFIED,
        self::CLAIMED,
        self::BANKED,
        self::SUBMITTED
    ];

    const INVALID_STATUSES = [
        self::USED,
        self::CANCELLED,
        self::EXPIRED
    ];

    // Access Document types
    const STAFF_CREDENTIAL = 'staff_credential';
    const RPT = 'reduced_price_ticket';
    const GIFT = 'gift_ticket';
    const VEHICLE_PASS = 'vehicle_pass';
    const WAP = 'work_access_pass';
    const WAPSO = 'work_access_pass_so';

    const ALL_EAT_PASS = 'all_eat_pass';
    const EVENT_EAT_PASS = 'event_eat_pass';
    const EVENT_RADIO = 'event_radio';
    const WET_SPOT = 'wet_spot';
    const WET_SPOT_POG = 'wet_spot_pog'; // unused currently, might be used someday by the HQ Window Interface

    const TICKET_TYPES = [
        self::STAFF_CREDENTIAL,
        self::RPT,
        self::GIFT
    ];

    const DELIVERABLE_TYPES = [
        self::STAFF_CREDENTIAL,
        self::RPT,
        self::GIFT,
        self::VEHICLE_PASS
    ];

    const PROVISION_TYPES = [
        self::ALL_EAT_PASS,
        self::EVENT_EAT_PASS,
        self::EVENT_RADIO,
        self::WET_SPOT,
    ];

    const EAT_PASSES = [
        self::ALL_EAT_PASS,
        self::EVENT_EAT_PASS,
    ];

    const HAS_ACCESS_DATE_TYPES = [
        self::STAFF_CREDENTIAL,
        self::WAP,
        self::WAPSO
    ];

    const EXPIRE_THIS_YEAR_TYPES = [
        self::WAP,
        self::WAPSO,
        self::VEHICLE_PASS,
        self::EVENT_RADIO
    ];

    const TYPE_LABELS = [
        self::STAFF_CREDENTIAL => 'Staff Credential',
        self::RPT => 'Reduced-Price Ticket',
        self::GIFT => 'Gift Ticket',
        self::VEHICLE_PASS => 'Vehicle Pass',
        self::WAP => 'WAP',
        self::WAPSO => 'WAPSO',

        self::ALL_EAT_PASS => 'All Eat Pass',
        self::EVENT_EAT_PASS => 'Event Week Eat Pass',
        self::EVENT_RADIO => 'Event Radio',
        self::WET_SPOT => 'Wet Spot Access',
        self::WET_SPOT_POG => 'Wet Spot Pog',
    ];

    const DELIVERY_NONE = 'none';
    const DELIVERY_POSTAL = 'postal';
    const DELIVERY_EMAIL = 'email';
    const DELIVERY_WILL_CALL = 'will_call';

    protected $fillable = [
        'person_id',
        'type',
        'is_job_provision',
        'status',
        'source_year',
        'access_date',
        'access_any_time',
        'name',
        'item_count',
        'comments',
        'expiry_date',
        'modified_date',
        'additional_comments',
        'delivery_method',
        'street1',
        'street2',
        'city',
        'state',
        'postal_code',
        'country'
    ];

    protected $casts = [
        'access_date' => 'datetime',
        'expiry_date' => 'datetime:Y-m-d',
        'create_date' => 'datetime',
        'modified_date' => 'datetime',
        'past_expire_date' => 'boolean',
        'access_any_time' => 'boolean',
        'is_job_provision' => 'boolean',
    ];

    protected $hidden = [
        'person',
        'additional_comments',   // pseudo-column, write-only. used to append to comments.
    ];

    protected $appends = [
        'past_expire_date',
        'has_staff_credential'
    ];

    protected $attributes = [
        'delivery_method' => 'none'
    ];

    public static function boot()
    {
        parent::boot();

        self::saving(function ($model) {
            if (empty($model->item_count)) {
                $model->item_count = 0;
            }

            if (empty($model->create_date)) {
                $model->create_date = now();
            }

            if (in_array($model->type, self::EXPIRE_THIS_YEAR_TYPES)) {
                $year = current_year();
                // Certain things always expire this year
                if (!$model->expiry_date || Carbon::parse($model->expiry_date)->year > $year) {
                    $model->expiry_date = $year;
                }
            }

            switch ($model->type) {
                case self::WAP:
                case self::WAPSO:
                    $model->delivery_method = self::DELIVERY_EMAIL;
                    break;
                case self::STAFF_CREDENTIAL:
                    $model->delivery_method = self::DELIVERY_WILL_CALL;
                    break;
                case self::EVENT_EAT_PASS:
                case self::WET_SPOT:
                case self::ALL_EAT_PASS:
                case self::WET_SPOT_POG:
                    $model->delivery_method = self::DELIVERY_NONE;
                    break;
            }

            // Only WAP SOs can have names
            if ($model->type != self::WAPSO) {
                $model->name = null;
            }

            // Only SCs and WAPs have access dates
            if (!in_array($model->type, self::HAS_ACCESS_DATE_TYPES)) {
                $model->access_date = null;
                $model->access_any_time = false;
            }
        });
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Save function. Don't allow a job provision to be banked.
     * @param $options
     * @return bool
     */

    public function save($options = []) {
        if ($this->is_job_provision && $this->status == self::BANKED) {
            $this->addError('status', 'Item is a job provision and cannot be banked');
            return false;
        }

        return parent::save($options);
    }

    /**
     * Find records matching a given criteria.
     *
     * Query parameters are:
     *  all: find all current documents otherwise only current documents (not expired, used, invalid)
     *  person_id: find for a specific person
     *  year: for a specific year
     *
     * @param $query
     * @return Collection
     */

    public static function findForQuery($query): Collection
    {
        $sql = self::orderBy('source_year')->orderBy('type');

        $status = $query['status'] ?? null;
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;
        $type = $query['type'] ?? null;

        if ($status != 'all') {
            if (empty($status)) {
                $sql->whereNotIn('status', self::INVALID_STATUSES);
            } else {
                $sql->whereIn('status', is_array($status) ? $status : explode(',', $status));
            }
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($type) {
            $sql->whereIn('type', is_array($type) ? $type : explode(',', $type));
        }

        if ($year) {
            $sql->where('source_year', $year);
        }

        return $sql->get();
    }

    /**
     * Check to see if an available ticket exists -- must have an unbanked ticket.
     *
     * @param int $personId
     * @return bool
     */

    public static function noAvailableTickets(int $personId): bool
    {
        return self::where('person_id', $personId)
            ->whereIn('type', self::TICKET_TYPES)
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::SUBMITTED])
            ->doesntExist();
    }

    /**
     * Build up a ticketing package for the person
     *
     * @param int $personId
     * @return array
     */

    public static function buildPackageForPerson(int $personId): array
    {
        $year = event_year() - 1;
        if ($year == 2020 || $year == 2021) {
            // 2020 & 2021 didn't happen. :-(
            $year = 2019;
        }

        return [
            'access_documents' => self::findForQuery(['person_id' => $personId]),
            'credits_earned' => Timesheet::earnedCreditsForYear($personId, $year),
            'year_earned' => $year,
            'period' => setting('TicketingPeriod')
        ];
    }

    /**
     * Find all available (qualified, claimed, banked) deliverables (tickets & vehicle pass) for a person
     *
     * @param int $personId
     * @return Collection
     */

    public static function findAllAvailableDeliverablesForPerson(int $personId): Collection
    {
        return self::where('person_id', $personId)
            ->whereIn('type', self::DELIVERABLE_TYPES)
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::BANKED])
            ->get();
    }

    /**
     * Find a document that is available (qualified, claimed, banked, submitted) for the given person & type(s)
     *
     * @param int $personId
     * @param array|string $type
     * @return AccessDocument|null
     */

    public static function findAvailableTypeForPerson(int $personId, array|string $type, $isJobProvision = null): ?AccessDocument
    {
        if (!is_array($type)) {
            $type = [$type];
        }

        $sql = self::where('person_id', $personId)
            ->whereIn('type', $type)
            ->whereIn('status', [
                self::QUALIFIED,
                self::CLAIMED,
                self::BANKED,
                self::SUBMITTED
            ]);

        if ($isJobProvision !== null) {
            $sql->where('is_job_provision', $isJobProvision);
        }

        return $sql->first();
    }

    /**
     * Find all item types for a given person, and mark as submitted (consumed).
     * (Don't allow people to bank an item if it was set on the BMID directly.)
     *
     * @param int $personId
     * @param array $type
     */

    public static function markSubmittedForBMID(int $personId, array $type)
    {
        $rows = self::whereIn('type', $type)
            ->where('person_id', $personId)
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::BANKED])
            ->get();

        foreach ($rows as $row) {
            $row->status = self::SUBMITTED;
            $changes = $row->getChangedValues();
            $row->additional_comments = 'Consumed by BMID export';
            $row->auditReason = 'Consumed by BMID export';
            $row->saveWithoutValidation();
            AccessDocumentChanges::log($row, Auth::id(), $changes);
        }
    }

    /**
     * Find a candidate WAP for a person
     *
     * @param int $personId
     * @return AccessDocument|null
     */

    public static function findWAPForPerson(int $personId)
    {
        $rows = self::where('person_id', $personId)
            ->whereIn('type', [self::STAFF_CREDENTIAL, self::WAP])
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::BANKED, self::SUBMITTED])
            ->orderBy('source_year')
            ->get();
        $wap = null;

        foreach ($rows as $row) {
            if ($row->status == self::CLAIMED || $row->status == self::SUBMITTED) {
                return $row;
            }

            if ($wap == null || $row->access_date == null) {
                $wap = $row;
            } elseif ($wap->access_date && $wap->access_date->gt($row->access_date)) {
                $wap = $row;
            }
        }

        return $wap;
    }

    /**
     * Find the work access pass for folks
     * @param $personIds
     * @return array
     */

    public static function findWAPForPersonIds($personIds): array
    {
        $waps = self::whereIntegerInRaw('person_id', $personIds)
            ->whereIn('type', [self::STAFF_CREDENTIAL, self::WAP])
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::BANKED, self::SUBMITTED])
            ->orderBy('source_year')
            ->get()
            ->groupBy('person_id');

        $people = [];

        foreach ($waps as $personId => $rows) {
            $wap = null;
            foreach ($rows as $row) {
                if ($row->status == self::CLAIMED || $row->status == self::SUBMITTED) {
                    $wap = $row;
                    break;
                }
                if ($wap == null || $wap->access_date == null) {
                    $wap = $row;
                } else if ($wap->access_date == null) {
                    continue;
                } else if ($wap->access_date->gt($row->access_date)) {
                    $wap = $row;
                }
            }

            $people[$personId] = $wap;
        }

        return $people;
    }

    /**
     * Update all non-submitted WAP & Staff Credentials with new access date
     *
     * @param int $personId
     * @param Carbon|string|null $accessDate
     * @param bool $accessAnyTime
     * @param string $reason
     */

    public static function updateWAPsForPerson(int  $personId, Carbon|string|null $accessDate,
                                               bool $accessAnyTime, string $reason)
    {
        if (empty($accessDate)) {
            $accessDate = null;
        }

        $rows = self::where('person_id', $personId)
            ->whereIn('type', [self::STAFF_CREDENTIAL, self::WAP])
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::BANKED])
            ->get();

        $userId = Auth::id();
        foreach ($rows as $row) {
            $row->access_date = $accessDate;
            $row->access_any_time = $accessAnyTime;
            $row->auditReason = $reason;
            $changes = $row->getChangedValues();
            $row->saveWithoutValidation();

            if (!empty($changes)) {
                AccessDocumentChanges::log($row, $userId, $changes);
            }
        }
    }

    /**
     * Find all the Significant Other WAPs for a person and year
     *
     * @param int $personId person to find
     * @return Collection
     */

    public static function findSOWAPsForPerson(int $personId): Collection
    {
        return self::where('type', self::WAPSO)
            ->where('person_id', $personId)
            ->whereNotIn('status', self::INVALID_STATUSES)
            ->get();
    }

    /**
     * Count how many (current) Significant Other WAP's for a person & year
     *
     * @param int $personId person to find
     * @return int
     */

    public static function SOWAPCount(int $personId): int
    {
        return self::where('person_id', $personId)
            ->where('type', self::WAPSO)
            ->whereNotIn('status', self::INVALID_STATUSES)
            ->count();
    }

    /**
     * Find a record belonging to a person.
     * @param $personId
     * @param $id
     * @return AccessDocument
     */

    public static function findForPerson($personId, $id): AccessDocument
    {
        return self::where('person_id', $personId)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Create a Significant Others Work Access Pass and claim it.
     *
     * @param integer $personId person to create for
     * @param integer $year year to create for
     * @param string $name the SO's name.
     */

    public static function createSOWAP($personId, $year, $name)
    {
        $wap = new AccessDocument;
        $wap->person_id = $personId;
        $wap->name = $name;
        $wap->type = self::WAPSO;
        $wap->status = self::CLAIMED;
        $wap->access_date = setting('TAS_DefaultSOWAPDate');
        $wap->source_year = $year;
        $wap->expiry_date = $year;
        $wap->save();

        return $wap;
    }

    /*
     * Add a comment to the comments column.
     */

    public function addComment($comment, $user)
    {
        if ($user instanceof Person) {
            $user = $user->callsign;
        }
        $date = date('n/j/y G:i:s');
        $this->comments = "$date $user: $comment\n{$this->comments}";
    }

    /**
     * Setter for expiry_date. Fixup the date if its only a year.
     *
     * @param $date
     */

    public function setExpiryDateAttribute($date)
    {
        if (is_numeric($date)) {
            $date = (string)$date;
        }

        if (strlen($date) == 4) {
            $date .= "-09-15 00:00:00";
        }

        $this->attributes['expiry_date'] = $date;
    }

    /**
     * Setter for access_date. Fix up the date to NULL (aka unspecified entry time)
     * if passed an empty value.
     *
     * @param $date
     */

    public function setAccessDateAttribute($date)
    {
        $this->attributes['access_date'] = empty($date) ? null : $date;
    }

    /**
     * Return true if the document expired
     *
     * @return bool
     */

    public function getPastExpireDateAttribute(): bool
    {
        return ($this->expiry_date && $this->expiry_date->year < current_year());
    }

    /**
     * Return true if the person claimed a SC for the year
     *
     * @return bool
     */

    public function getHasStaffCredentialAttribute(): bool
    {
        return ($this->attributes['has_staff_credential'] ?? false);
    }

    /**
     * additional_comments, when set, pre-appends to the comments column with
     * a timestamp and current user's callsign.
     *
     * @param $value
     */

    public function setAdditionalCommentsAttribute($value)
    {
        if (empty($value)) {
            return;
        }

        $date = date('n/j/y G:i:s');
        $user = Auth::user();
        $callsign = $user ? $user->callsign : "(unknown)";
        $this->comments = "$date $callsign: $value\n" . $this->comments;
    }

    /**
     * Is this item a ticket?
     *
     * @return bool
     */

    public function isTicket(): bool
    {
        return in_array($this->type, self::TICKET_TYPES);
    }

    public function getTypeLabel()
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function setStreet1Attribute($value)
    {
        $this->attributes['street1'] = $value ?? '';
    }

    public function setStreet2Attribute($value)
    {
        $this->attributes['street2'] = $value ?? '';
    }

    public function setCityAttribute($value)
    {
        $this->attributes['city'] = $value ?? '';
    }

    public function setStateAttribute($value)
    {
        $this->attributes['state'] = $value ?? '';
    }

    public function setPostalCodeAttribute($value)
    {
        $this->attributes['postal_code'] = $value ?? '';
    }

    public function setCountryAttribute($value)
    {
        $this->attributes['country'] = $value ?? '';
    }

    public function hasAddress(): bool
    {
        foreach (['street1', 'city', 'state', 'postal_code'] as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }

        return true;
    }
}
