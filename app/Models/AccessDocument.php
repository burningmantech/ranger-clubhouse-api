<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class AccessDocument extends ApiModel
{
    protected $table = 'access_document';
    protected bool $auditModel = true;

    // Statuses
    const BANKED = 'banked';
    const CANCELLED = 'cancelled';
    const CLAIMED = 'claimed';
    const EXPIRED = 'expired';
    const QUALIFIED = 'qualified';
    const SUBMITTED = 'submitted';
    const TURNED_DOWN = 'turned_down';
    const USED = 'used';

    const ACTIVE_STATUSES = [
        self::QUALIFIED,
        self::CLAIMED,
        self::BANKED
    ];

    const CURRENT_STATUSES = [
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
    const GIFT = 'gift_ticket';
    const LSD = 'lsd_ticket';
    const RPT = 'reduced_price_ticket';
    const STAFF_CREDENTIAL = 'staff_credential';
    const VEHICLE_PASS = 'vehicle_pass';
    const VEHICLE_PASS_GIFT = 'vehicle_pass_gift';
    const VEHICLE_PASS_LSD = 'vehicle_pass_lsd';
    const WAP = 'work_access_pass';
    const WAPSO = 'work_access_pass_so';

    const TICKET_TYPES = [
        self::GIFT,
        self::LSD,
        self::RPT,
        self::STAFF_CREDENTIAL,
    ];

    const REGULAR_TICKET_TYPES = [
        self::RPT,
        self::STAFF_CREDENTIAL,
    ];

    const SPECIAL_TICKET_TYPES = [
        self::GIFT,
        self::LSD,
    ];

    const SPECIAL_VP_TYPES = [
        self::VEHICLE_PASS_LSD,
        self::VEHICLE_PASS_GIFT
    ];

    const DELIVERABLE_TYPES = [
        self::GIFT,
        self::LSD,
        self::RPT,
        self::STAFF_CREDENTIAL,
        self::VEHICLE_PASS
    ];

    const HAS_ACCESS_DATE_TYPES = [
        self::STAFF_CREDENTIAL,
        self::WAP,
        self::WAPSO
    ];

    const EXPIRE_THIS_YEAR_TYPES = [
        self::GIFT,
        self::LSD,
        self::VEHICLE_PASS,
        self::VEHICLE_PASS_GIFT,
        self::VEHICLE_PASS_LSD,
        self::WAP,
        self::WAPSO,
    ];

    const TYPE_LABELS = [
        self::GIFT => 'Gift Ticket',
        self::LSD => 'LSD Ticket',
        self::RPT => 'Reduced-Price Ticket',
        self::STAFF_CREDENTIAL => 'Staff Credential',
        self::VEHICLE_PASS => 'Vehicle Pass',
        self::VEHICLE_PASS_GIFT => 'Vehicle Pass (Gift)',
        self::VEHICLE_PASS_LSD => 'Vehicle Pass (LSD)',
        self::WAP => 'WAP',
        self::WAPSO => 'SO WAP',
    ];

    const SHORT_TICKET_LABELS = [
        self::GIFT => 'GIFT',
        self::LSD => 'LSD',
        self::RPT => 'RPT',
        self::STAFF_CREDENTIAL => 'SC',
        self::VEHICLE_PASS => 'VP',
        self::VEHICLE_PASS_GIFT => 'VPGIFT',
        self::VEHICLE_PASS_LSD => 'VPLSD',
        self::WAP => 'WAP',
        self::WAPSO => 'SO WAP',
    ];

    const DELIVERY_NONE = 'none';
    const DELIVERY_POSTAL = 'postal';
    const DELIVERY_EMAIL = 'email';
    const DELIVERY_WILL_CALL = 'will_call';

    protected $fillable = [
        'person_id',
        'type',
        'status',
        'source_year',
        'access_date',
        'access_any_time',
        'name',
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
        'country',
    ];

    protected $casts = [
        'access_date' => 'datetime',
        'expiry_date' => 'datetime:Y-m-d',
        'create_date' => 'datetime',
        'modified_date' => 'datetime',
        'past_expire_date' => 'boolean',
        'access_any_time' => 'boolean',
    ];

    protected $hidden = [
        'additional_comments',   // pseudo-column, write-only. used to append to comments.
    ];

    protected $appends = [
        'past_expire_date',
        'has_staff_credential',
    ];

    protected $attributes = [
        'delivery_method' => 'none',
    ];

    public function access_document_changes(): HasMany
    {
        return $this->hasMany(AccessDocumentChanges::class, 'record_id');
    }

    public static function boot(): void
    {
        parent::boot();

        self::saving(function ($model) {
            if (empty($model->create_date)) {
                $model->create_date = now();
            }

            if ($model->doesExpireThisYear()) {
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

                case self::LSD:
                case self::VEHICLE_PASS_LSD:
                    /*
                     * LSD items are uploaded to the Clubhouse AFTER the recipient has stated they want the goodies.
                     * End-users are only told about the items and not be given an opportunity to
                     */
                    if ($model->status == self::QUALIFIED) {
                        $model->status = self::CLAIMED;
                    }
                    break;
            }

            // Only Gift, LSD, and WAP SOs can have names
            if ($model->type != self::WAPSO
                && $model->type != self::LSD
                && $model->type != self::GIFT) {
                $model->name = null;
            }

            // Only SCs and WAPs have access dates
            if (!in_array($model->type, self::HAS_ACCESS_DATE_TYPES)) {
                $model->access_date = null;
                $model->access_any_time = false;
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
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
        $sql = self::orderBy('type');

        $status = $query['status'] ?? null;
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;
        $type = $query['type'] ?? null;
        $includePerson = $query['include_person'] ?? null;

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

        if ($includePerson) {
            $sql->with('person:id,callsign');
        } else {
            $sql->orderBy('source_year');
        }

        $rows = $sql->get();
        if ($includePerson) {
            return $rows->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
        } else {
            return $rows;
        }
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
            ->whereIn('type', self::REGULAR_TICKET_TYPES)
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::SUBMITTED])
            ->doesntExist();
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
     * Find all item types for a given person, and mark as submitted (consumed).
     * (Don't allow people to bank an item if it was set on the BMID directly.)
     *
     * @param int $personId
     * @param array $type
     */

    public static function markSubmittedForBMID(int $personId, array $type): void
    {
        $rows = self::whereIn('type', $type)
            ->where('person_id', $personId)
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED])
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

    public static function findWAPForPerson(int $personId): ?AccessDocument
    {
        $rows = self::where('person_id', $personId)
            ->whereIn('type', [self::STAFF_CREDENTIAL, self::WAP])
            ->whereIn('status', [self::QUALIFIED, self::CLAIMED, self::BANKED, self::SUBMITTED])
            ->orderBy('source_year')
            ->get();

        $wap = self::wapCandidate($rows->filter(
            fn($row) => ($row->status == self::CLAIMED || $row->status == self::SUBMITTED))
        );
        if ($wap) {
            return $wap;
        }

        return self::wapCandidate($rows);
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
            // A person may have a SC & WAP.. Happens when the person needs to arrive sooner than
            // what the submitted SC is. Access dates cannot change once the SC has been submitted.
            $wap = self::wapCandidate($rows->filter(
                fn($row) => ($row->status == self::CLAIMED || $row->status == self::SUBMITTED))
            );
            if (!$wap) {
                $wap = self::wapCandidate($rows);
            }

            $people[$personId] = $wap;
        }

        return $people;
    }

    /**
     * Find the most appropriate WAP candidate record. The first record with any time access takes priority,
     * followed by the earliest access date.
     *
     * @param $rows
     * @return ?AccessDocument
     */

    public static function wapCandidate($rows): ?AccessDocument
    {
        $wap = null;
        foreach ($rows as $row) {
            if ($row->access_any_time) {
                return $row;
            }
            if ($wap == null) {
                $wap = $row;
            } else if ($wap->access_date && $wap->access_date->gt($row->access_date)) {
                $wap = $row;
            }
        }
        return $wap;
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
     * Count how many (current) Significant Other WAPs for a person & year
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
     * @param int $personId person to create for
     * @param int $year year to create for
     * @param string $name the SO's name.
     * @return AccessDocument
     */

    public static function createSOWAP(int $personId, int $year, string $name): AccessDocument
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

    /**
     * Add a comment to the comments column.
     *
     * @param string|null $comment
     * @param $user
     */

    public function addComment(?string $comment, $user)
    {
        if ($user instanceof Person) {
            $user = $user->callsign;
        }
        $date = date('n/j/y G:i:s');
        $this->comments = "$date $user: $comment\n{$this->comments}";
    }

    /**
     * Setter for expiry_date. Fix the date if it's only a year.
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
     * Is this a RPT or SC ticket?
     *
     * @return bool
     */

    public function isRegularTicket(): bool
    {
        return in_array($this->type, self::TICKET_TYPES);
    }

    /**
     * Is this a special ticket (Gift or LSD)?
     *
     * @return bool
     */

    public function isSpecialTicket(): bool
    {
        return in_array($this->type, self::SPECIAL_TICKET_TYPES);
    }

    public function isAvailable(): bool
    {
        return $this->status == AccessDocument::QUALIFIED
            || $this->status == AccessDocument::CLAIMED
            || $this->status == AccessDocument::SUBMITTED;
    }

    public function getTypeLabel()
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getShortTypeLabel()
    {
        return self::SHORT_TICKET_LABELS[$this->type] ?? $this->type;
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

    /**
     * Does the document have all the necessary address fields?
     *
     * @return bool
     */

    public function hasAddress(): bool
    {
        foreach (['street1', 'city', 'state', 'postal_code'] as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Does this access document type expire after the event?
     *
     * @return bool
     */

    public function doesExpireThisYear(): bool
    {
        return in_array($this->type, self::EXPIRE_THIS_YEAR_TYPES);
    }
}
