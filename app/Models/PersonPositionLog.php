<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $person_id
 * @property int $position_id
 * @property Carbon|string|null $left_on
 * @property Carbon|string|null $joined_on
 */
class PersonPositionLog extends ApiModel
{
    protected $table = 'person_position_log';
    protected $auditModel = true;
    public $timestamps = true;

    protected $guarded = [
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'joined_on' => 'date:Y-m-d',
        'left_on' => 'date:Y-m-d',
        'updated_at' => 'datetime',
    ];

    protected $rules = [
        'person_id' => 'required|exists:person,id',
        'position_id' => 'required|exists:position,id',
        'joined_on' => 'required',
        'left_on' => 'nullable|sometimes|after_or_equal:joined_on'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Find position log records based on the given criteria
     *
     * @param array $query
     * @return mixed
     */

    public static function findForQuery(array $query): mixed
    {
        $personId = $query['person_id'] ?? null;
        $positionId = $query['position_id'] ?? null;

        $sql = self::query();

        if ($personId) {
            $sql->where('person_id', $personId);
        } else {
            $sql->with('person:id,callsign');
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        } else {
            $sql->with('position:id,title,active,type');
        }

        return $sql->get()->sortBy($positionId ? 'joined_on' : ['position.title', 'joined_on'])->values();
    }

    public function loadRelationships()
    {
        $this->load(['person:id,callsign', 'position:id,title,active,type']);
    }

    /**
     * Log a position grant
     *
     * @param int $positionId
     * @param int $personId
     */

    public static function addPerson(int $positionId, int $personId)
    {
        self::insert(['person_id' => $personId, 'position_id' => $positionId, 'joined_on' => now()]);
    }

    /**
     * Log a position revoke
     *
     * @param int $positionId
     * @param int $personId
     */

    public static function removePerson(int $positionId, int $personId)
    {
        self::where(['person_id' => $personId, 'position_id' => $positionId])->update(['left_on' => now()]);
    }

    /**
     * Set the left_on column to null or a Carbon object.
     *
     * @param $date
     * @return void
     */

    public function setLeftOnAttribute($date)
    {
        $this->attributes['left_on'] = empty($date) ? null : Carbon::parse($date);
    }
}