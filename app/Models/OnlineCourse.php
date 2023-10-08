<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class OnlineCourse extends ApiModel
{
    protected $table = 'online_course';
    protected bool $auditModel = true;
    public $timestamps = false;

    const COURSE_FOR_ALL = 'all';
    const COURSE_FOR_RETURNING = 'returning';
    const COURSE_FOR_NEW = 'new';

    public $fillable = [
        'course_for',
        'course_id',
        'name',
        'position_id',
        'year'
    ];

    public $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $rules = [
        'course_id' => 'required|string',
        'course_for' => 'required|string',
        'position_id' => 'required|exists:position,id',
        'name' => 'sometimes|string',
        'year' => 'required|integer',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function person_online_course(): HasMany
    {
        return $this->hasMany(PersonOnlineCourse::class);
    }

    public static function findForQuery($query): Collection
    {
        $year = $query['year'] ?? null;
        $positionId = $query['position_id'] ?? null;

        $sql = self::query();

        if ($year) {
            $sql->where('year', $year);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        return $sql->orderBy('year')
            ->orderBy('position_id')
            ->with('position:id,title,active')
            ->get();
    }

    /**
     * Find a online course for the given position, year, and course.
     *
     * @param int $positionId
     * @param int $year
     * @param string $for
     * @return OnlineCourse|null
     */

    public static function findForPositionYear(int $positionId, int $year, string $for) : ?OnlineCourse
    {
        return self::where(['position_id' => $positionId, 'year' => $year, 'course_for' => $for])->first();
    }


    public function save($options = []): bool
    {
        $unique = ['position_id' => $this->position_id, 'year' => $this->year, 'course_for' => $this->course_for];
        if ($this->exists) {
            $this->rules['position_id'] = [
                'required',
                Rule::unique('online_course')
                    ->where(fn(Builder $q) => $q->where('id', '!=', $this->id)->where($unique))
            ];
        } else {
            $this->rules['position_id'] = [
                'required',
                Rule::unique('online_course')->where(fn(Builder $q) => $q->where($unique))
            ];
        }

        return parent::save($options);
    }

    public function name() : Attribute
    {
        return BlankIfEmptyAttribute::make();
    }
}