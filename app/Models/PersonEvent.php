<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Collection;

class PersonEvent extends ApiModel
{
    protected $table = 'person_event';
    protected $auditModel = true;

    use HasCompositePrimaryKey;

    protected $primaryKey = ['person_id', 'year'];

    protected $fillable = [
        'may_request_stickers',
        'org_vehicle_insurance',
        'signed_motorpool_agreement',
        'signed_personal_vehicle_agreement',
        'asset_authorized',
        'timesheet_confirmed_at',
        'timesheet_confirmed',
        'sandman_affidavit',
    ];

    protected $attributes = [
        'may_request_stickers' => false,
        'org_vehicle_insurance' => false,
        'signed_motorpool_agreement' => false,
        'signed_personal_vehicle_agreement' => false,
        'asset_authorized' => false,
        'timesheet_confirmed' => false,
        'sandman_affidavit' => false,
    ];

    protected $appends = [
        'id'        // Not real.
    ];

    protected $createRules = [
        'person_id' => 'required|integer|exists:person,id',
        'year' => 'required|integer'
    ];

    public static function findForQuery($query)
    {
        $personId = $query['person_id'] ?? null;
        $year = $query['year'] ?? null;

        $sql = self::with('person:id,callsign');
        if ($personId) {
            $sql->where('person_id', $personId);
        }
        if ($year) {
            $sql->where('year', $year);
        }

        return $sql->get()->sortBy('person.callsign')->values();
    }

    public static function findForRoute($key)
    {
        list ($personId, $year) = explode('-', $key);
        return self::firstOrNewForPersonYear($personId, $year);
    }

    public static function firstOrNewForPersonYear($personId, $year)
    {
        $row = self::find(['person_id' => $personId, 'year' => $year]);
        if ($row) {
            return $row;
        }

        $row = new self;
        $row->person_id = $personId;
        $row->year = $year;
        return $row;
    }

    public static function findForPersonYear($personId, $year)
    {
        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    /**
     * Can the given person and year be allowed to submit requests for vehicle stickers?
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */
    public static function mayRequestStickersForYear(int $personId, int $year) : bool
    {
        return self::where('person_id', $personId)->where('year', $year)->where('may_request_stickers', true)->exists();
    }

    public function person() {
        return $this->belongsTo(Person::class);
    }

    public function getIdAttribute()
    {
        return $this->person_id . '-' . $this->year;
    }

    /**
     * Find all records for a given list of ids and year
     *
     * @param $ids
     * @param $year
     * @return PersonEvent[]|Collection
     */

    public static function findAllForIdsYear($ids, $year)
    {
        return self::where('year', $year)->whereIntegerInRaw('person_id', $ids)->get();
    }
}
