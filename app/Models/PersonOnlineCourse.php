<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PersonOnlineCourse extends ApiModel
{
    protected $table = 'person_online_course';

    protected bool $auditModel = true;

    protected $casts = [
        'completed_at' => 'datetime',
        'enrolled_at' => 'datetime',
    ];

    // Table is not directly accessible
    protected $guarded = [];

    const TYPE_MOODLE = 'moodle';
    const TYPE_MANUAL_REVIEW = 'manual-review'; // prior to 2020

    protected $attributes = [
        'type' => self::TYPE_MOODLE,
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function online_course(): BelongsTo
    {
        return $this->belongsTo(OnlineCourse::class);
    }

    /**
     * Find the online course completion records based on the given query.
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $sql = self::select('person_online_course.*')
            ->with(['person:id,callsign,status'])
            ->join('person', 'person.id', 'person_online_course.person_id');

        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $type = $query['type'] ?? null;
        $courseId = $query['online_course_id'] ?? null;
        $positionId = $query['position_id'] ?? null;

        if ($year) {
            $sql->whereYear('completed_at', $year);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($type) {
            $sql->where('type', $type);
        }

        if ($courseId) {
            $sql->where('online_course_id', $courseId);
        }

        if ($positionId) {
            $sql->where('position_id', $positionId);
        }

        return $sql->get()->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Find a person's online course  for given year and position
     *
     * @param int $personId
     * @param int $year
     * @param int $positionId
     * @return PersonOnlineCourse|null
     */

    public static function findForPersonYear(int $personId, int $year, int $positionId): ?PersonOnlineCourse
    {
        return self::where('year', $year)
            ->where('person_id', $personId)
            ->where('position_id', $positionId)
            ->first();
    }

    /**
     * Did the given person complete the online course in a specific year?
     *
     * @param int $personId
     * @param int $year
     * @param int $positionId
     * @return bool
     */

    public static function didCompleteForYear(int $personId, int $year, int $positionId): bool
    {
        return self::where('person_id', $personId)
            ->where('year', $year)
            ->where('position_id', $positionId)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * Did the given person complete a specific course?
     *
     * @param int $personId
     * @param OnlineCourse $course
     * @return bool
     */

    public static function didCompleteCourse(int $personId, OnlineCourse $course): bool
    {
        return self::where('person_id', $personId)
            ->where('online_course_id', $course->id)
            ->where('year', $course->year)
            ->whereNotNull('completed_at')
            ->exists();
    }

    public static function firstOrNewForPersonYear(int $personId, int $year, int $positionId): PersonOnlineCourse
    {
        return self::firstOrNew([
            'person_id' => $personId,
            'year' => $year,
            'position_id' => $positionId,
        ]);
    }
}
