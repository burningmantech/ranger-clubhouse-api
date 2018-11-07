<?php

namespace App\Models;

use App\Models\ApiModel;
use Illuminate\Database\Eloquent\Builder;

class AccessDocumentDelivery extends ApiModel
{
    protected $table = 'access_document_delivery';

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

    /*
     * Eloquent does not handle composite keys by default.
     * However, this method may be overriden to do it.
     */

    protected function setKeysForSaveQuery(Builder $query)
    {
        return $query->where('person_id', $this->person_id)
                    ->where('year', $this->year);
    }

    public function getIdAttribute() {
        return "{$this->year}{$this->person_id}";
    }

    public function getKey() {
        return $this->getIdAttribute();
    }

    /* Used by router to inject the model into the controller method */
    public static function findById($id) {
        $year = substr($id, 0, 4);
        $person = substr($id, 4);

        return self::where('person_id', $person)->where('year', $year)->first();
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

    public function save($options = [])
    {
        if ($this->method == 'mail' && !$this->validate($this->mailRules)) {
            return false;
        }

        return parent::save($options);
    }


}
