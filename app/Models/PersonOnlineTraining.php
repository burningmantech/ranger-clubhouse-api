<?php

namespace App\Models;

use Illuminate\Support\Collection;

class PersonOnlineTraining extends ApiModel
{
    protected $table = 'person_online_training';

    protected $auditModel = true;

    protected $casts = [
        'completed_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    // Table is not directly accessible
    protected $guarded = [];

    const MOODLE = 'moodle';
    const MANUAL_REVIEW = 'manual-review'; // prior to 2020

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find the online course completion records based on the given query.
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $sql = self::select('person_online_training.*')
            ->with(['person:id,callsign,status'])
            ->join('person', 'person.id', 'person_online_training.person_id');

        $year = $query['year'] ?? null;
        $personId = $query['person_id'] ?? null;
        $type = $query['type'] ?? null;

        if ($year) {
            $sql->whereYear('completed_at', $year);
        }

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($type) {
            $sql->where('type', $type);
        }

        return $sql->get()->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /**
     * Find a person's online course completion for given year
     *
     * @param int $personId
     * @param int $year
     * @return PersonOnlineTraining|null
     */

    public static function findForPersonYear(int $personId, int $year): ?PersonOnlineTraining
    {
        return self::whereYear('completed_at', $year)
            ->where('person_id', $personId)
            ->orderBy('completed_at', 'desc')
            ->first();
    }

    /**
     * Did the given person complete the online course in a specific year?
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */

    public static function didCompleteForYear(int $personId, int $year): bool
    {
        return self::where('person_id', $personId)
            ->whereYear('completed_at', $year)
            ->exists();
    }
}
