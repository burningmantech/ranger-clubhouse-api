<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\ApiModel;
use App\Models\Person;

use Carbon\Carbon;

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

    const ACTIVE_STATUSES = [
        self::QUALIFIED,
        self::CLAIMED,
        self::BANKED
    ];

    const INVALID_STATUSES = [
        self::USED,
        self::CANCELLED,
        self::EXPIRED
    ];

    const TICKET_TYPES = [
        self::STAFF_CREDENTIAL,
        self::RPT,
        self::GIFT
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

    protected $fillable = [
        'person_id',
        'type',
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
        'access_any_time' => 'boolean'
    ];

    protected $hidden = [
        'person',
        'additional_comments',   // pseudo-column, write-only. used to append to comments.
    ];

    protected $appends = [
        'past_expire_date',
        'has_staff_credential'
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

    /*
     * Find records matching a given criteria.
     *
     * Query parameters are:
     *  all: find all current documents otherwise only current documents (not expired, used, invalid)
     *  person_id: find for a specific person
     *  year: for a specific year
     */

    public static function findForQuery($query)
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
        if ($year == 2020) {
            // 2020 didn't happen. :-(
            $year = 2019;
        }

        return [
            'access_documents' => self::findForQuery(['person_id' => $personId]),
            'delivery' => AccessDocumentDelivery::findForPersonYear($personId, current_year()),
            'credits_earned' => Timesheet::earnedCreditsForYear($personId, $year),
            'year_earned' => $year,
        ];
    }

    /**
     * Find a document that is available (qualified, claimed, banked, submitted) for the given person & type(s)
     *
     * @param int $personId
     * @param array|string $type
     * @return AccessDocument|null
     */

    public static function findAvailableTypeForPerson(int $personId, array|string $type)
    {
        if (!is_array($type)) {
            $type = [$type];
        }

        return self::where('person_id', $personId)
            ->whereIn('type', $type)
            ->whereIn('status', [
                self::QUALIFIED,
                self::CLAIMED,
                self::BANKED,
                self::SUBMITTED
            ])->first();
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
                ->whereIn('status', [ self::QUALIFIED, self::CLAIMED, self::BANKED ])
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

    /*
     * Retrieve all access documents, group by people, that are claimed, qualified, or banked
     *
     * Return an array. Each element is an associative array:
     *
     * person info: id,callsign,status,first_name,last_name,email
     *     if $includeDelivery is true include - street1,city,state,zip,country
     * documents: array of access documents
     */

    public static function retrieveCurrentByPerson($forDelivery)
    {
        $currentYear = current_year();

        if ($forDelivery) {
            $sql = self::where('status', self::CLAIMED);
        } else {
            $sql = self::whereIn('status', self::ACTIVE_STATUSES);
        }

        $sql->whereNotIn('type', self::PROVISION_TYPES);

        $rows = $sql->select(
            '*',
            DB::raw('EXISTS (SELECT 1 FROM access_document sc WHERE sc.person_id=access_document.person_id AND sc.type="staff_credential" AND sc.status IN ("claimed", "submitted") LIMIT 1) as has_staff_credential')
        )
            ->with(['person:id,callsign,status,first_name,last_name,email,home_phone,street1,street2,city,state,zip,country'])
            ->orderBy('source_year')
            ->get();

        $people = [];


        $dateRange = setting('TAS_WAPDateRange');
        if ($dateRange) {
            list($low, $high) = explode("-", trim($dateRange));
        } else {
            $low = 5;
            $high = 26;
        }

        if ($rows->isNotEmpty()) {
            $deliveriesByPerson = AccessDocumentDelivery::whereIn('person_id', $rows->pluck('person_id'))
                ->where('year', $currentYear)
                ->get()
                ->keyBy('person_id');
        }

        foreach ($rows as $row) {
            // skip deleted person records
            if (!$row->person) {
                continue;
            }

            $personId = $row->person_id;

            if (!isset($people[$personId])) {
                $people[$personId] = (object)[
                    'person' => $row->person,
                    'documents' => []
                ];
            }

            $person = $people[$personId];
            $person->documents[] = $row;

            $errors = [];
            switch ($row->type) {
                case self::STAFF_CREDENTIAL:
                case self::WAP:
                case self::WAPSO:
                    if (!$row->access_any_time) {
                        $accessDate = $row->access_date;
                        if (!$accessDate) {
                            $errors[] = "missing access date";
                        } elseif ($accessDate->year < $currentYear) {
                            $errors[] = "access date [$accessDate] is less than current year [$currentYear]";
                        } else {
                            $day = $accessDate->day;
                            if ($day < $low || $day > $high) {
                                $errors[] = "access date [$accessDate] outside day [$day] range low [$low], high [$high]";
                            }
                        }
                    }
                    break;
            }

            if ($forDelivery) {
                $delivery = $deliveriesByPerson->get($row->person_id);
                $deliveryMethod = $delivery ? $delivery->method : 'unknown';
                $person->delivery_method = $deliveryMethod;
                $deliveryType = 'unknown';

                switch ($row->type) {
                    case self::STAFF_CREDENTIAL:
                        $deliveryType = AccessDocumentDelivery::STAFF_CREDENTIALING;
                        break;

                    case self::WAP:
                    case self::WAPSO:
                        $deliveryType = 'print_at_home';
                        break;

                    case self::RPT:
                        $deliveryType = $deliveryMethod;
                        if ($deliveryMethod == 'unknown') {
                            $errors[] = 'missing delivery method';
                        }
                        break;

                    case self::VEHICLE_PASS:
                    case self::GIFT:
                        if ($row->type == self::VEHICLE_PASS && $row->has_staff_credential) {
                            $deliveryType = AccessDocumentDelivery::STAFF_CREDENTIALING;
                        } else if ($deliveryMethod == 'unknown') {
                            $errors[] = 'missing delivery method';
                        } else if ($deliveryMethod == AccessDocumentDelivery::MAIL) {
                            if ($delivery->hasAddress()) {
                                $row->delivery_address = [
                                    'street' => $delivery->street,
                                    'city' => $delivery->city,
                                    'state' => $delivery->state,
                                    'postal_code' => $delivery->postal_code,
                                    'country' => 'US',
                                    'phone' => $row->person->home_phone,
                                ];
                            } else {
                                $errors[] = 'missing mailing address';
                            }
                            $deliveryType = AccessDocumentDelivery::MAIL;
                        } else {
                            $deliveryType = AccessDocumentDelivery::WILL_CALL;
                        }
                        break;
                }

                $row->delivery_type = $deliveryType;
            }

            if (!empty($errors)) {
                $row->error = implode(';', $errors);
                $row->has_error = true;
            }
        }

        usort($people, fn($a, $b) => strcasecmp($a->person->callsign, $b->person->callsign));

        return [
            'people' => $people,
            'day_high' => (int)$high,
            'day_low' => (int)$low,
        ];
    }

    /*
     * Retrieve all people with expiring tickets for a given year.
     */

    public static function retrieveExpiringTicketsByPerson($year)
    {
        $rows = self::whereIn('type', self::TICKET_TYPES)
            ->whereIn('status', [self::QUALIFIED, self::BANKED])
            ->whereYear('expiry_date', $year)
            ->with(['person:id,callsign,email,status'])
            ->orderBy('source_year')
            ->get();

        $peopleByIds = [];
        foreach ($rows as $row) {
            if (!$row->person) {
                continue;
            }

            $personId = $row->person->id;
            if (!isset($peopleByIds[$personId])) {
                $peopleByIds[$personId] = [
                    'person' => $row->person,
                    'tickets' => []
                ];
            }

            $peopleByIds[$personId]['tickets'][] = [
                'id' => $row->id,
                'type' => $row->type,
                'status' => $row->status,
                'expiry_date' => (string)$row->expiry_date,
            ];
        }

        $people = array_values($peopleByIds);
        usort($people, fn($a, $b) => strcasecmp($a['person']->callsign, $b['person']->callsign));

        return $people;
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

    /*
     * Find the work access pass for folks
     */

    public static function findWAPForPersonIds($personIds)
    {
        $waps = self::whereIn('person_id', $personIds)
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
    public static function updateWAPsForPerson(int $personId, Carbon|string|null $accessDate,
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
     * @return AccessDocument[]|Collection
     */

    public static function findSOWAPsForPerson(int $personId)
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
     */

    public static function SOWAPCount(int $personId)
    {
        return self::where('person_id', $personId)
            ->where('type', self::WAPSO)
            ->whereNotIn('status', self::INVALID_STATUSES)
            ->count();
    }

    /*
     * Find a record belonging to a person.
     */

    public static function findForPerson($personId, $id)
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

    public function addComment($comment, $callsign)
    {
        $date = date('n/j/y G:i:s');
        $this->comments = "$date $callsign: $comment\n{$this->comments}";
    }

    /*
     * Setter for expiry_date. Fixup the date if its only a year.
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

    /*
     * Setter for access_date. Fix up the date to NULL (aka unspecified entry time)
     * if passed an empty value.
     */

    public function setAccessDateAttribute($date)
    {
        $this->attributes['access_date'] = empty($date) ? null : $date;
    }

    /*
     * Return true if the document expired
     */

    public function getPastExpireDateAttribute()
    {
        return ($this->expiry_date && $this->expiry_date->year < current_year());
    }

    /*
     * Return true if the person claimed a SC for the year
     */

    public function getHasStaffCredentialAttribute()
    {
        return ($this->attributes['has_staff_credential'] ?? false);
    }

    /*
     * additional_comments, when set, pre-appends to the comments column with
     * a timestamp and current user's callsign.
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

    public function isTicket()
    {
        return in_array($this->type, self::TICKET_TYPES);
    }

    public function getTypeLabel()
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
