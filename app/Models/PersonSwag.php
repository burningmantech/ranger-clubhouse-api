<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSwag extends ApiModel
{
    protected $table = 'person_swag';
    protected $auditModel = true;
    public $timestamps = true;

    protected $fillable = [
        'person_id',
        'swag_id',
        'year_issued',
        'notes'
    ];

    protected $attributes = [
        'notes' => '',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function swag(): BelongsTo
    {
        return $this->belongsTo(Swag::class);
    }

    public function loadRelationships(): PersonSwag
    {
        return $this->load('swag');
    }

    /**
     * Find all the swags given to a person
     *
     * @param array $query
     * @return mixed
     */

    public static function findForQuery(array $query): mixed
    {
        $personId = $query['person_id'] ?? null;
        $includePerson = $query['include_person'] ?? null;
        $swagId = $query['swag_id'] ?? null;
        $yearIssued = $query['year_issued'] ?? null;

        $sql = self::with('swag');
        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($swagId) {
            $sql->where('swag_id', $swagId);
        }

        if ($yearIssued) {
            $sql->where('year_issued', $yearIssued);
        }

        if ($includePerson) {
            $sql->with('person:id,callsign,status');
        }

        return $sql->get()->sortBy('swag.title', SORT_NATURAL)->values();
    }


    /**
     * Set the notes.
     *
     * @return Attribute
     */

    protected function notes(): Attribute
    {
        return Attribute::make(
            set: fn($value) => empty($value) ? '' : $value
        );
    }

    /**
     * Set the year issued column. Null out if empty.
     *
     * @return Attribute
     */

    protected function yearIssued(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}
