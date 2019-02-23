<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;

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
        'past_expire_date'
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
                $model->expiry_date = date('Y');
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
        $currentYear = date('Y');

        if ($forDelivery) {
            $sql = self::where('status', 'claimed');
        } else {
            $sql = self::whereIn('status', self::ACTIVE_STATUSES);
        }

        $personIds = $sql->distinct('person_id')->pluck('person_id');

        $personWith = 'person:id,callsign,status,first_name,last_name,email';

        if ($forDelivery) {
            $personWith .= ",street1,city,state,zip,country";
        }

        $rows = self::whereNotIn('status', self::INVALID_STATUSES)
            ->whereIn('person_id', $personIds)
            ->with([ $personWith ])
            ->get();

        $people = [];

        if ($forDelivery) {
            $deliveries = AccessDocumentDelivery::whereIn('person_id', $personIds)
                ->where('year', $currentYear)
                ->get()
                ->keyBy('person_id');
        }

        $dateRange = config('clubhouse.TAS_WAPDateRange');
        if ($dateRange) {
            list($low, $high) = explode("-", $dateRange);
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

            if ($forDelivery) {
                $delivery = $deliveries->has($personId) ? $deliveries[$personId] : null;
                $deliveryMethod = $delivery ? $delivery->method : 'will_call';
            }

            if (!isset($people[$personId])) {
                $people[$personId] = (object) [
                    'person'    => $row->person,
                    'documents' => []
                ];

                if ($forDelivery) {
                    $people[$personId]->delivery_method = $deliveryMethod;
                }
            }


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

            $person = $people[$personId];
            $person->documents[] = $row;

            if ($forDelivery) {
                $deliveryType = "UNKNOWN";
                switch ($row->type) {
                    case 'staff_credential':
                        $deliveryType = 'WILL_CALL';
                        break;

                    case 'work_access_pass':
                    case 'work_access_pass_so':
                        $deliveryType = 'PRINT_AT_HOME';
                        break;

                    case 'vehicle_pass':
                    case 'gift_ticket':
                    case 'reduced_price_ticket':
                        if ($deliveryMethod == 'mail') {
                            $address = [];
                            if ($delivery->country == "United States") {
                                $country = 'US';
                                $deliveryType = "USPS";
                            } elseif ($delivery->country == "Canada") {
                                $country = 'CA';
                                $deliveryType = "CANADA_UPS";
                            } else {
                                $country = $delivery->country;
                            }
                            $row->delivery_address = [
                            'street'    => $delivery->street,
                            'city'      => $delivery->city,
                            'state'     => substr(strtoupper($delivery->state), 0, 2),
                            'postal_code' => $delivery->postal_code,
                            'country'   => $country,
                            ];
                        } else {
                            $deliveryType = "WILL_CALL";
                        }
                        break;
                }

                $row->delivery_type = $deliveryType;

                /*
                 * Lulu wants us to include Clubhouse addresses for everyone,
                 * even those not getting something shipped, for some reason.
                 * But, we cannot include EU countries because of GDPR.
                 */

                if ($deliveryType == "WILL_CALL" || $deliveryType == "PRINT_AT_HOME") {
                    $info = $row->person;
                    if (!self::isEUCountry($info->country)) {
                        $row->delivery_address = [
                            'street'    => $info->street1,
                            'city'      => $info->city,
                            'state'     => substr(strtoupper($info->state), 0, 2),
                            'postal_code'   => $info->zip,
                            'country'   => $info->country
                        ];
                    }
                }
            }
        }

        usort(
            $people,
            function ($a, $b) {
                return strcmp($a->person->callsign, $b->person->callsign);
            }
        );

        return [
            'people'   => $people,
            'day_high' => $high,
            'day_low'  => $low,
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
                return strcmp($a['person']->callsign, $b['person']->callsign);
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
            ->get();
        $wap = null;

        foreach ($rows as $row) {
            if ($wap == null || $row->access_date == null) {
                $wap = $row;
            } elseif ($wap->access_date->gt($row->access_date)) {
                $wap = $row;
            }
        }

        return $wap;
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
        $wap->access_date = config('clubhouse.TAS_DefaultSOWAPDate');
        $wap->source_year = $year;
        $wap->expiry_date = $year;
        $wap->save();

        return $wap;
    }

    /*
     * Setter for expiry_date. Fixup the date if its only a year.
     */

    public function setExpiryDateAttribute($date)
    {
        if (is_string($date) && strlen($date) == 4
        || is_numeric($date)) {
            $date .= "-09-15 00:00:00";
        }

        $this->attributes['expiry_date'] = $date;
    }

    /*
     * Return true if the document expired
     */

    public function getPastExpireDateAttribute()
    {
        return ($this->expiry_date && $this->expiry_date->year < date('Y'));
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
