<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PersonAward extends ApiModel
{
    protected $table = 'person_award';
    protected bool $auditModel = true;
    public $timestamps = true;

    protected $fillable = [
        'person_id',
        'award_id',
        'notes'
    ];

    protected $attributes = [
        'notes' => '',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }

    public function loadRelationships(): PersonAward
    {
        return $this->load('award');
    }

    /**
     * Find all the awards given to a person
     *
     * @param array $query
     * @return mixed
     */

    public static function findForQuery(array $query): mixed
    {
        $personId = $query['person_id'] ?? null;
        $awardId = $query['award_id'] ?? null;

        $sql = self::with('award');
        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($awardId) {
            $sql->where('award_id', $awardId);
        }

        return $sql->get()->sortBy('award.title', SORT_NATURAL)->values();
    }

    /**
     * Does the person have the award?
     *
     * @param int $awardId
     * @param int $personId
     * @return bool
     */

    public static function haveAward(int $awardId, int $personId): bool
    {
        return self::where(['award_id' => $awardId, 'person_id' => $personId])->exists();
    }

    /**
     * Has the given people been granted the award?
     *
     * @param int $awardId
     * @param array $ids
     * @return array
     */

    public static function haveAwardForIds(int $awardId, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = DB::table('person_award')
            ->where('award_id', $awardId)
            ->whereIntegerInRaw('person_id', $ids)
            ->get()
            ->keyBy('person_id');

        $haveAward = [];
        foreach ($rows as $personId => $award) {
            $haveAward[] = $personId;
        }

        return $haveAward;
    }

    /**
     * Set the notes
     */

    public function notes(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }
}
