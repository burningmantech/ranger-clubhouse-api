<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonIntake extends ApiModel
{
    protected $table = 'person_intake';
    public $timestamps = true;

    protected $guarded = [
        'person_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public static function findForPersonYearOrNew(int $personId, int $year): PersonIntake
    {
        $ps = self::where(['person_id' => $personId, 'year' => $year])->first();
        if ($ps) {
            return $ps;
        }

        $ps = new self;
        $ps->person_id = $personId;
        $ps->year = $year;
        return $ps;
    }

    public static function retrievePersonnelIssueForIdsYear($personIds, $year): array
    {
        return self::select('person_id')
            ->whereIntegerInRaw('person_id', $personIds)
            ->where('personnel_rank', 4)
            ->where('year', $year)
            ->pluck('person_id')
            ->toArray();
    }

    public function rrnRank(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function mentorRank(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function vcRank(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }

    public function personnelRank(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}
