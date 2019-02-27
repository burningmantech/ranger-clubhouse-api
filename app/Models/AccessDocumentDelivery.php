<?php

namespace App\Models;

use App\Models\ApiModel;
use Illuminate\Database\Eloquent\Builder;

use App\Traits\HasCompositePrimaryKey;

class AccessDocumentDelivery extends ApiModel
{
    use HasCompositePrimaryKey;

    protected $table = 'access_document_delivery';

    protected $primaryKey = [ 'person_id', 'year' ];

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
        'method'     => 'required',
        'person_id'  => 'required',
        'year'       => 'required',
    ];

    protected $mailRules = [
        'street'      => 'required',
        'city'        => 'required',
        'state'       => 'required',
        'postal_code' => 'required',
        'country'     => 'required'
    ];

    public static function findForRoute($id) {
        list ($personId, $year) = explode(':', $id);

        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    public function getIdAttribute() {
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

    public static function findOrCreateForPersonYear($personId, $year)
    {
        return self::firstOrCreate([
                    'person_id' => $personId,
                    'year'      => $year,
                ]);
    }

    public function save($options = [])
    {
        if ($this->method == 'mail' && !$this->validate($this->mailRules)) {
            return false;
        }

        return parent::save($options);
    }


}
