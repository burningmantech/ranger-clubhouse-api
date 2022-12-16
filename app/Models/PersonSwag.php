<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
            $sql->where('year_issued', $yearIssuede);
        }

        return $sql->get()->sortBy('swag.title', SORT_NATURAL)->values();
    }

    /**
     * Does the person have the swag?
     *
     * @param int $swagIg
     * @param int $personId
     * @return bool
     */

    public static function haveSwag(int $swagIg, int $personId): bool
    {
        return self::where(['swag_id' => $swagIg, 'person_id' => $personId])->exists();
    }

    /**
     * Set the notes
     *
     * @param string|null $value
     * @return void
     */

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = empty($value) ? '' : $value;
    }

    /**
     * Set the year issued column. Null out if empty.
     *
     * @param $value
     * @return void
     */

    public function setYearIssuedAttribute($value): void
    {
        $this->attributes['year_issued'] = empty($value) ? null : $value;
    }
}
