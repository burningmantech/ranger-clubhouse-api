<?php

namespace App\Models;

use App\Attributes\NullIfEmptyAttribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPerson extends ApiModel
{
    protected $table = 'asset_person';
    protected $auditModel = true;

    protected $fillable = [
        'person_id',
        'asset_id',
        'checked_out',
        'checked_in',
        'check_out_person_id',
        'check_int_person_id',
        'attachment_id'
    ];

    protected $casts = [
        'checked_out' => 'datetime',
        'checked_in' => 'datetime',
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'asset_id' => 'required|integer',
    ];

    const RELATIONSHIPS = [
        'asset',
        'attachment',
        'person:id,callsign',
        'check_out_person:id,callsign',
        'check_in_person:id,callsign',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function check_out_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function check_in_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(AssetAttachment::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function loadRelationships()
    {
        $this->load(self::RELATIONSHIPS);
    }

    /**
     * Find assets belonging to a year and/or person.
     *
     * year: checked out year
     * person_id: person to search for if absent, person callsign will also be looked up
     *
     * @param array $query conditions to look up
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $sql = self::with(self::RELATIONSHIPS);

        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;

        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($year) {
            $sql->whereYear('checked_out', $year);
        }

        return $sql->orderBy('checked_out')->get();
    }

    /**
     * Find the check out/in history for an asset.
     *
     * @param $assetId
     * @return Collection|array
     */

    public static function retrieveHistory($assetId): Collection|array
    {
        return self::where('asset_id', $assetId)
            ->with(['person:id,callsign', 'attachment', 'check_out_person', 'check_in_person'])
            ->get();
    }

    /**
     * Find if a person has checked out an asset.
     *
     * @param $assetId
     * @return ?Model
     */

    public static function findCheckedOutPerson($assetId): ?Model
    {
        return self::where('asset_id', $assetId)
            ->whereNull('checked_in')
            ->with('person:id,callsign')
            ->first();
    }

    /**
     * Set the attachment_id column, set to null if value is empty
     *
     * @return Attribute
     */

    protected function attachmentId(): Attribute
    {
        return NullIfEmptyAttribute::make();
    }
}
