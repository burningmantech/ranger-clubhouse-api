<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class PersonIntakeNote extends ApiModel
{
    protected $table = 'person_intake_note';
    public $timestamps = true;

    protected $auditModel = true;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $rules = [
        'note' => 'sometimes|string|nullable|max:64000'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function person_source(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find all the intake notes for the given people, and return
     * the results grouped by the ids
     *
     * @param  $pnvIds
     * @param int $year include all records up to the year
     * @param $notePersonId
     * @return Collection
     */

    public static function retrieveHistoryForPersonIds($pnvIds, int $year, $notePersonId): Collection
    {
        $sql = self::whereIntegerInRaw('person_id', $pnvIds)
            ->where('year', '<=', $year)
            ->orderBy('person_id')
            ->orderBy('created_at')
            ->with('person_source:id,callsign');

        if ($notePersonId) {
            $sql->where('person_source_id', $notePersonId);
        }

        return $sql->get()->groupBy('person_id');
    }

    /**
     * Find all the intake notes for a person
     *
     * @param int $personId
     * @param string $type
     * @return Collection
     */
    public static function findAllForPersonType(int $personId, string $type): Collection
    {
        return self::where('person_id', $personId)
            ->where('type', $type)
            ->orderBy('created_at')
            ->with('person_source:id,callsign')
            ->get();
    }

    /**
     * Record the intake notes for a person
     *
     * @param int $personId Person to record
     * @param int $year Year to record in
     * @param string $noteType note type (vc, rrn, mentor)
     * @param string $note the note itself
     * @param bool $isLog true if the note is an audit note about the rank
     * @return void
     */
    public static function record(int $personId, int $year, string $noteType, string $note, bool $isLog = false): void
    {
        $user = Auth::user();

        self::create([
            'person_id' => $personId,
            'year' => $year,
            'type' => $noteType,
            'note' => $note,
            'is_log' => $isLog,
            'person_source_id' => $user ? $user->id : 0
        ]);
    }
}
