<?php

namespace App\Models;

class PersonIntake extends ApiModel
{
    protected $table = 'person_intake';
    public $timestamps = true;

    protected $guarded = [
        'person_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function person(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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

    public function setRrnRankAttribute($value)
    {
        $this->attributes['rrn_rank'] = empty($value) ? null : $value;
    }

    public function setMentorRankAttribute($value)
    {
        $this->attributes['mentor_rank'] = empty($value) ? null : $value;
    }

    public function setVcRankAttribute($value)
    {
        $this->attributes['vc_rank'] = empty($value) ? null : $value;
    }

    public function setPersonnelRankAttribute($value)
    {
        $this->attributes['personnel_rank'] = empty($value) ? null : $value;
    }

}
