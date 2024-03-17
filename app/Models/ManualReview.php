<?php

namespace App\Models;

/*
 * Manual Review (aka that Google Form) -- retained for archival purposes.
 */

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualReview extends ApiModel
{
    protected $table = 'manual_review';

    protected $fillable = [
        'person_id',
        'passdate',
    ];

    protected function casts(): array
    {
        return [
            'passdate' => 'datetime'
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForQuery($query)
    {
        $sql = self::select('manual_review.*')
            ->with(['person:id,callsign,status'])
            ->join('person', 'person.id', 'manual_review.person_id');

        if (isset($query['year'])) {
            $sql->whereYear('passdate', $query['year']);
        }

        if (isset($query['person_id'])) {
            $sql->where('person_id', $query['person_id']);
        }

        return $sql->get()->sortBy('person.callsign', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public static function findForPersonYear($personId, $year)
    {
        return self::whereYear('passdate', $year)->where('person_id', $personId)->orderBy('passdate', 'desc')->first();
    }
}
