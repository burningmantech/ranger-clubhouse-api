<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Models\ApiModel;
use App\Models\Person;
use App\Models\AccessDocumentDelivery;

use App\Helpers\SqlHelper;

use Carbon\Carbon;

class AccessDocument extends ApiModel
{
    protected $table = 'access_document';

    const ACTIVE_STATUSES = [
        'qualified',
        'claimed',
        'banked'
    ];

    const INVALID_STATUSES = [
        'used',
        'cancelled',
        'expired'
    ];

    const TICKET_TYPES = [
        'staff_credential',
        'reduced_price_ticket',
        'gift_ticket'
    ];

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
//        'create_date', set by model
        'modified_date',
        'additional_comments',
    ];

    protected $casts = [
        'access_date'   => 'datetime',
        'expiry_date'   => 'datetime:Y-m-d',
        'create_date'   => 'datetime',
        'modified_date' => 'datetime',
        'past_expire_date' => 'boolean',
    ];

    protected $hidden = [
        'person',
        'additional_comments'   // pseudo-column, write-only. used to append to comments.
    ];

    protected $appends = [
        'past_expire_date',
        'has_staff_credential'
    ];

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            // TODO - adjust access_document schema to default to current timestamp
            if ($model->create_date == null) {
                $model->create_date = SqlHelper::now();
            }
        });

        self::saving(function ($model) {
            // Certain things always expire this year
            if (in_array($model->type, [ "work_access_pass", "work_access_pass_so", "vehicle_pass"])) {
                $model->expiry_date = current_year();
            }

            // Only SO WAPs can have names
            if ($model->type != "work_access_pass_so") {
                $model->name = null;
            }

            // Only SCs and WAPs have access dates
            if ($model->type != "staff_credential" &&
                $model->type != "work_access_pass" &&
                $model->type != "work_access_pass_so") {
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

        if (empty($query['all'])) {
            $sql = $sql->whereNotIn('status', self::INVALID_STATUSES);
        }

        if (isset($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        if (isset($query['year'])) {
            $sql = $sql->where('source_year', $query['year']);
        }

        return $sql->get();
    }

    /*
     * Retrieve all access documents that are claimed, qualified, or banked
     * group by people.
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
            $sql = self::where('status', 'claimed');
        } else {
            $sql = self::whereIn('status', self::ACTIVE_STATUSES);
        }

        $rows = $sql->select(
                '*',
                DB::raw('EXISTS (SELECT 1 FROM access_document sc WHERE sc.person_id=access_document.person_id AND sc.type="staff_credential" AND sc.status IN ("claimed", "submitted") LIMIT 1) as has_staff_credential')
            )
            ->with([ 'person:id,callsign,status,first_name,last_name,email,home_phone,street1,street2,city,state,zip,country' ])
            ->orderBy('source_year')
            ->get();

        $people = [];

        if ($forDelivery) {
            $personIds = $rows->pluck('person_id')->unique()->toArray();
            $deliveries = AccessDocumentDelivery::whereIn('person_id', $personIds)
                ->where('year', $currentYear)
                ->get()
                ->keyBy('person_id');
        }

        $dateRange = setting('TAS_WAPDateRange');
        if ($dateRange) {
            list($low, $high) = explode("-", trim($dateRange));
        } else {
            $low = 5;
            $high = 26;
        }

        foreach ($rows as $row) {
            // skip deleted person records
            if (!$row->person) {
                continue;
            }

            $personId = $row->person_id;

            if (!isset($people[$personId])) {
                $people[$personId] = (object) [
                    'person'    => $row->person,
                    'documents' => []
                ];
            }

            $person = $people[$personId];
            $person->documents[] = $row;

            switch ($row->type) {
                case 'work_access_pass':
                case 'work_access_pass_so':
                case 'staff_credential':
                    if (!$row->access_any_time) {
                        $accessDate = $row->access_date;
                        if (!$accessDate) {
                            $row->has_error = true;
                            $row->error =  "missing access date";
                        } elseif ($accessDate->year < $currentYear) {
                            $row->has_error = true;
                            $row->error =  "access date [$accessDate] is less than current year [$currentYear]";
                        } else {
                            $day = $accessDate->day;
                            if ($day < $low || $day > $high) {
                                $row->has_error = true;
                                $row->error = "access date [$accessDate] outside day [$day] range low [$low], high [$high]";
                            }
                        }
                    }
                    break;
            }

            if ($forDelivery) {
                $delivery = $deliveries[$personId] ?? null;
                $deliveryMethod = $delivery ? $delivery->method : 'will_call';
                $person->delivery_method = $deliveryMethod;
                $deliveryType = "UNKNOWN";

                switch ($row->type) {
                    case 'staff_credential':
                        $deliveryType = 'staff_credentialing';
                        break;

                    case 'work_access_pass':
                    case 'work_access_pass_so':
                        $deliveryType = 'print_at_home';
                        break;

                    case 'reduced_price_ticket':
                        $deliveryType = $deliveryMethod;
                        break;

                    case 'vehicle_pass':
                    case 'gift_ticket':
                        if ($row->type == 'vehicle_pass' && $row->has_staff_credential) {
                            $deliveryType = 'staff_credentialing';
                        } else if ($deliveryMethod == 'mail') {
                            $row->delivery_address = [
                                'street'      => $delivery->street,
                                'city'        => $delivery->city,
                                'state'       => $delivery->state,
                                'postal_code' => $delivery->postal_code,
                                'country'     => 'US',
                                'phone'       => $row->person->home_phone,
                            ];
                            $deliveryType = 'mail';
                        } else {
                            $deliveryType = 'will_call';
                        }
                        break;
                }

                $row->delivery_type = $deliveryType;
            }
        }

        usort(
            $people,
            function ($a, $b) {
                return strcasecmp($a->person->callsign, $b->person->callsign);
            }
        );

        return [
            'people'   => $people,
            'day_high' => (int) $high,
            'day_low'  => (int) $low,
        ];
    }

    /*
     * Retrieve all people with expiring tickets for a given year.
     */

    public static function retrieveExpiringTicketsByPerson($year)
    {
        $rows = self::whereIn('type', self::TICKET_TYPES)
            ->whereIn('status', [ 'qualified', 'banked' ])
            ->whereYear('expiry_date', $year)
            ->with([ 'person:id,callsign,email,status'])
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
                    'person'    => $row->person,
                    'tickets'   => []
                ];
            }

            $peopleByIds[$personId]['tickets'][] = [
                'id'          => $row->id,
                'type'        => $row->type,
                'status'      => $row->status,
                'expiry_date' => (string) $row->expiry_date,
            ];
        }

        $people = array_values($peopleByIds);
        usort(
            $people,
            function ($a, $b) {
                return strcasecmp($a['person']->callsign, $b['person']->callsign);
            }
        );

        return $people;
    }

    /*
     * Find the work access pass for a person
     */

    public static function findWAPForPerson($personId)
    {
        $rows = self::where('person_id', $personId)
            ->whereIn('type', [ 'staff_credential', 'work_access_pass' ])
            ->whereIn('status', [ 'qualified', 'claimed', 'banked', 'submitted' ])
            ->orderBy('source_year')
            ->get();
        $wap = null;

        foreach ($rows as $row) {
            if ($row->status == 'claimed' || $row->status == 'submitted') {
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
            ->whereIn('type', [ 'staff_credential', 'work_access_pass' ])
            ->whereIn('status', [ 'qualified', 'claimed', 'banked', 'submitted' ])
            ->orderBy('source_year')
            ->get()
            ->groupBy('person_id');

        $people = [];

        foreach ($waps as $personId => $rows) {
            $wap = null;
            foreach ($rows as $row) {
                if ($row->status == 'claimed' || $row->status == 'submitted') {
                    $wap = $row;
                    break;
                }
                if ($wap == null || $row->access_date == null) {
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
     *
     * Update all non-submitted WAPs for a person
     */

    public static function updateWAPsForPerson($personId, $accessDate, $accessAnyTime)
    {
        if (empty($accessDate)) {
            $accessDate = null;
        }

        $rows = self::where('person_id', $personId)
            ->whereIn('type', [ 'staff_credential', 'work_access_pass'])
            ->whereIn('status', [ 'qualified', 'claimed', 'banked' ])
            ->get();

        $user = Auth::user();
        $userId = $user ? $user->id : 0;

        foreach ($rows as $row) {
            $row->access_date = $accessDate;
            $row->access_any_time = $accessAnyTime;
            $changes = $row->getChangedValues();
            $row->saveWithoutValidation();
            if (!empty($changes)) {
                AccessDocumentChanges::log($row, $userId, $changes);
            }
        }
    }

    /**
     *
     * Find all the Significant Other WAPs for a person and year
     *
     * @param integer $personId person to find
     * @param integer $year year to search
     */

    public static function findSOWAPsForPerson($personId, $year)
    {
        return self::where('type', 'work_access_pass_so')
            ->where('person_id', $personId)
            ->whereNotIn('status', self::INVALID_STATUSES)
            ->get();
    }

    /**
     * Count how many (current) Significant Other WAP's for a person & year
     *
     * @param integer $personId person to find
     * @param integer $year year to search
     */

    public static function SOWAPCount($personId, $year)
    {
        return self::where('person_id', $personId)
            ->where('type', 'work_access_pass_so')
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
        $wap->type = 'work_access_pass_so';
        $wap->status = 'claimed';
        $wap->access_date = setting('TAS_DefaultSOWAPDate');
        $wap->source_year = $year;
        $wap->expiry_date = $year;
        $wap->save();

        return $wap;
    }

    /*
     * Add a comment to the comments column.
     */

    public function addComment($comment, $callsign) {
        $date = date('n/j/y G:i:s');
        $this->comments = "$date $callsign: $comment\n{$this->comments}";
    }

    /*
     * Setter for expiry_date. Fixup the date if its only a year.
     */

    public function setExpiryDateAttribute($date)
    {
        if (is_numeric($date)) {
            $date = (string) $date;
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
        $this->attributes['access_date'] = empty($date) ? NULL : $date;
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
     * Is the country code a EU country?
     */

    public static function isEUCountry($country)
    {
        return in_array($country, [
            "BE", "BG", "CZ", "DK", "DE", "EE", "IE",
            "EL", "ES", "FR", "HR", "IT", "CY", "LV", "LT", "LU",
            "HU", "MT", "NL", "AT", "PL", "PT", "RO", "SI", "SK",
            "FI", "SE", "UK", "GB"
        ]);
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
        $user = \Auth::user();
        $callsign = $user ? $user->callsign : "(unknown)";
        $this->comments = "$date $callsign: $value\n".$this->comments;
    }
}
