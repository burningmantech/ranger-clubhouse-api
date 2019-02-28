<?php

namespace App\Models;

use App\Models\ApiModel;

use App\Models\Person;
use App\Models\AssetAttachment;
use App\Models\Asset;

use DB;

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
        'checked_in'  => 'datetime',
    ];

    protected $rules = [
        'person_id' => 'required|integer',
        'asset_id'  => 'required|integer',
    ];

    const RELATIONSHIPS = [ 'asset', 'attachment', 'person:id,callsign' ];

    public function person() {
        return $this->belongsTo('App\Models\Person');
    }

    public function attachment() {
        return $this->belongsTo('App\Models\AssetAttachment');
    }

    public function asset() {
        return $this->belongsTo('App\Models\Asset');
    }

    public function loadRelationships() {
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
    public static function findForQuery($query) {
        $sql = self::with([ 'asset', 'attachment' ]);

        if (@$query['person_id']) {
            $sql = $sql->where('person_id', $query['person_id']);
        } else {
            $sql = $ql->with([ 'person:id,callsign' ]);
        }

        if (@$query['year']) {
            $sql = $sql->whereYear('checked_out', $query['year']);
        }

        return $sql->orderBy('checked_out')->get();
    }

    /**
     * Find if a person has checked out an asset.
     */

     public static function findCheckedOutPerson($assetId, $year)
     {
         return self::select('person.id', 'person.callsign')
                ->join('person', 'person.id', '=', 'asset_person.person_id')
                ->where('asset_id', $assetId)
                ->whereYear('checked_out', $year)
                ->whereNull('checked_in')
                ->first();
     }

     /**
      * Find all raidios that were checked out with duration.
      *
      * query types are:
      * year: the year to find radios, defaults to current year if not present
      * include_qualified: include people who qualified for event radios, otherwise find only shift qualified individuals
      * event_summary: report on all radios still checked out or was checked out over the hour limit.
      * hour_limit: rpeort on radios checked out for more than X hours. defaults to 14
      */

     public static function findRadiosCheckedOutForQuery($query) {
         $year = $query['year'] ?? date('Y');
         $sql = self::select(
                     'asset_person.person_id',
                     'person.callsign',
                     'asset_person.checked_out',
                     'asset_person.checked_in',
                     'asset.barcode',
                     'asset.perm_assign',
                     DB::raw('(UNIX_TIMESTAMP(IFNULL(asset_person.checked_in, NOW())) - UNIX_TIMESTAMP(asset_person.checked_out)) AS duration'),
                     DB::raw('IF(radio_eligible.max_radios > 0, true,false) AS eligible')
                 )
                 ->join('person', 'person.id', 'asset_person.person_id')
                 ->join('asset', 'asset.id', 'asset_person.asset_id')
                 ->whereYear('checked_out', $year)
                 ->where('description', 'radio');

         $sql->leftJoin('radio_eligible', function ($query) use ($year) {
                 $query->whereRaw('radio_eligible.person_id=asset_person.person_id');
                 $query->where('year', $year);
         });

         $seconds = ($query['hour_limit'] ?? 14) * 3600;

         if (isset($query['event_summary'])) {
             $sql->where(function($q) use ($seconds) {
                 $q->whereNull('checked_in');
                 $q->orWhereRaw("(UNIX_TIMESTAMP(asset_person.checked_in) - UNIX_TIMESTAMP(asset_person.checked_out)) > $seconds");
             });
         } else {
             $sql->whereRaw("(UNIX_TIMESTAMP() - UNIX_TIMESTAMP(asset_person.checked_out)) > $seconds")->whereNull('checked_in');
         }

         if (empty($query['include_qualified'])) {
             $sql->whereNull('radio_eligible.max_radios');
         }

         return $sql->orderBy('person.callsign')->orderBy('asset_person.checked_out')->get();
     }

}
