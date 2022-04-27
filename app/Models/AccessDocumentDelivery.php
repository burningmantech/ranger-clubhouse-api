<?php

namespace App\Models;

use App\Models\ApiModel;

use App\Traits\HasCompositePrimaryKey;

class AccessDocumentDelivery extends ApiModel
{
    use HasCompositePrimaryKey;

    protected $table = 'access_document_delivery';
    protected $auditModel = true;

    protected $primaryKey = ['person_id', 'year'];

    // Items to be sent thru the mail
    const MAIL = 'mail';
    // to be held at Box Office's Will Call
    const WILL_CALL = 'will_call';
    // Delivery not stated yet, or not applicable.
    const NONE = 'none';

    // The following two delivery methods are not part of the schema, and only
    // used to export a CSV file which is uploaded to ticketing.

    // to be held at the Gerlach office
    const STAFF_CREDENTIALING = 'staff_credentialing';
    // Item to be printed at home - i.e. WAPs & WAPSOs.
    const PRINT_AT_HOME = 'print_at_home';

    // Indicate there is no ID column.
    //public $incrementing = false;

    protected $fillable = [
        'person_id',
        'method',
        'year',
        'street',
        'city',
        'state',
        'postal_code',
        'country'
    ];

    protected $appends = [
        'id'        // No real ID
    ];

    protected $casts = [
        'modified_date' => 'timestamp',
    ];

    protected $rules = [
        'method' => 'required',
        'person_id' => 'required',
        'year' => 'required',
    ];

    protected $mailRules = [
        'street' => 'required',
        'city' => 'required',
        'state' => 'required',
        'postal_code' => 'required',
        'country' => 'required'
    ];

    protected $attributes = [
        'method' => self::NONE
    ];

    public static function findForRoute($id)
    {
        list ($personId, $year) = explode(':', urldecode($id));

        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    public function getIdAttribute()
    {
        return $this->person_id . ":" . $this->year;
    }

    public static function findForQuery($query)
    {
        $sql = self::orderBy('year', 'person_id');

        if (!empty($query['year'])) {
            $sql = $sql->where('year', $query['year']);
        }

        if (!empty($query['person_id'])) {
            $sql = $sql->where('person_id', $query['person_id']);
        }

        return $sql->get();
    }

    public static function findForPersonYear($personId, $year)
    {
        return self::where('person_id', $personId)
            ->where('year', $year)
            ->first();
    }

    public static function findOrNewForPersonYear($personId, $year)
    {
        return self::firstOrNew([
            'person_id' => $personId,
            'year' => $year,
        ]);
    }

    public static function retrieveForPersonIdsYear($personIds, $year)
    {
        return self::whereIntegerInRaw('person_id', $personIds)->where('year', $year)->get()->keyBy('person_id');
    }

    public function hasAddress() {
        foreach (['street', 'city', 'state', 'postal_code'] as $field) {
            if (empty($this->{$field})) {
                return false;
            }
        }

        return true;
    }
}
