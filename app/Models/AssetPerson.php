<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AssetPerson extends ApiModel
{
    protected $table = 'asset_person';
    protected $auditModel = true;

    protected $fillable = [
        'person_id',
        'asset_id',
        'checked_out',
        'checked_in',
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

    const RELATIONSHIPS = ['asset', 'attachment', 'person:id,callsign'];

    public function person()
    {
        return $this->belongsTo('App\Models\Person');
    }

    public function attachment()
    {
        return $this->belongsTo('App\Models\AssetAttachment');
    }

    public function asset()
    {
        return $this->belongsTo('App\Models\Asset');
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
     * @return Collection|array
     */

    public static function findForQuery($query): Collection|array
    {
        $sql = self::with(['asset', 'attachment']);

        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;

        if ($personId) {
            $sql->where('person_id', $personId);
        } else {
            $sql = $sql->with(['person:id,callsign']);
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
            ->with(['person:id,callsign', 'attachment'])
            ->get();
    }

    /**
     * Find if a person has checked out an asset.
     * @param $assetId
     * @return Builder|Model|null
     */

    public static function findCheckedOutPerson($assetId): Model|Builder|null
    {
        return self::where('asset_id', $assetId)
            ->whereNull('checked_in')
            ->with('person:id,callsign')
            ->first();
    }

    /**
     * Set the attachment_id column, set to null if value is empty
     *
     * @param $value
     */

    public function setAttachmentIdAttribute($value)
    {
        $this->attributes['attachment_id'] = empty($value) ? null : $value;
    }
}
