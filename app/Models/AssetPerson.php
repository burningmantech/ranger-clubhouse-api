<?php

namespace App\Models;

use App\Models\ApiModel;

use App\Models\Person;
use App\Models\AssetAttachment;
use App\Models\Asset;

use Illuminate\Support\Facades\DB;

class AssetPerson extends ApiModel
{
    protected $table = 'asset_person';

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
     *
     */
    public static function findForQuery($query)
    {
        $sql = self::with(['asset', 'attachment']);

        if (@$query['person_id']) {
            $sql = $sql->where('person_id', $query['person_id']);
        } else {
            $sql = $ql->with(['person:id,callsign']);
        }

        if (@$query['year']) {
            $sql = $sql->whereYear('checked_out', $query['year']);
        }

        return $sql->orderBy('checked_out')->get();
    }

    public static function retrieveHistory($assetId)
    {
        return self::where('asset_id', $assetId)
            ->with(['person:id,callsign', 'attachment'])
            ->get();
    }

    /**
     * Find if a person has checked out an asset.
     */

    public static function findCheckedOutPerson($assetId)
    {
        return self::where('asset_id', $assetId)
            ->whereNull('checked_in')
            ->with('person:id,callsign')
            ->first();
    }

    public function setAttachmentIdAttribute($value)
    {
        if (empty($value)) {
            $value = null;
        }

        $this->attributes['attachment_id'] = $value;
    }

}
