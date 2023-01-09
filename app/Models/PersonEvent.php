<?php

namespace App\Models;

use App\Traits\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int person_id
 * @property int year
 * @property bool asset_authorized
 * @property bool may_request_stickers
 * @property bool org_vehicle_insurance
 * @property bool sandman_affidavit
 * @property bool signed_motorpool_agreement
 * @property bool signed_nda
 * @property bool signed_personal_vehicle_agreement
 * @property bool timesheet_confirmed
 * @property Carbon timesheet_confirmed_at
 * @property ?string lms_course_id
 */
class PersonEvent extends ApiModel
{
    protected $table = 'person_event';
    protected $auditModel = true;

    use HasCompositePrimaryKey;

    protected $primaryKey = ['person_id', 'year'];

    protected $fillable = [
        'asset_authorized',
        'lms_course_id',
        'may_request_stickers',
        'org_vehicle_insurance',
        'pii_finished_at',
        'pii_started_at',
        'sandman_affidavit',
        'signed_motorpool_agreement',
        'signed_nda',
        'signed_personal_vehicle_agreement',
        'ticketing_finished_at',
        'ticketing_last_visited_at',
        'ticketing_started_at',
        'timesheet_confirmed',
        'timesheet_confirmed_at',
    ];

    protected $attributes = [
        'asset_authorized' => false,
        'may_request_stickers' => false,
        'org_vehicle_insurance' => false,
        'sandman_affidavit' => false,
        'signed_motorpool_agreement' => false,
        'signed_nda' => false,
        'signed_personal_vehicle_agreement' => false,
        'timesheet_confirmed' => false,
    ];

    protected $appends = [
        'id'        // Not real.
    ];

    protected $dates = [
        'pii_finished_at',
        'pii_started_at',
        'ticketing_finished_at',
        'ticketing_last_visited_at',
        'ticketing_started_at',
    ];

    protected $createRules = [
        'person_id' => 'required|integer|exists:person,id',
        'year' => 'required|integer'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Find records based on given query.
     *
     * @param $query
     * @return mixed
     */

    public static function findForQuery($query): mixed
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

    /**
     * Find record for API route - initialize (but don't create) the record if it doesn't exist.
     *
     * @param $key
     * @return PersonEvent|null
     */

    public static function findForRoute($key): ?PersonEvent
    {
        list ($personId, $year) = explode('-', $key);
        return self::firstOrNewForPersonYear($personId, $year);
    }

    /**
     * Find or initialize a person event record
     *
     * @param int $personId
     * @param int $year
     * @return PersonEvent
     */

    public static function firstOrNewForPersonYear(int $personId, int $year): PersonEvent
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

    /**
     * Find a record for a given person and year.
     *
     * @param $personId
     * @param $year
     * @return PersonEvent|null
     */

    public static function findForPersonYear($personId, $year): ?PersonEvent
    {
        return self::where('person_id', $personId)->where('year', $year)->first();
    }

    /**
     * Is the column true or false for the given person in the current year
     *
     * @param int $personId
     * @param string $column
     * @return bool
     */

    public static function isSet(int $personId, string $column): bool
    {
        return (bool)self::where('person_id', $personId)->where('year', current_year())->value($column);
    }

    /**
     * Get the pseudo-column id.
     *
     * @return string
     */

    public function getIdAttribute(): string
    {
        return $this->person_id . '-' . $this->year;
    }

    /**
     * Find all records for a given list of ids and year
     *
     * @param $ids
     * @param int $year
     * @return Collection
     */

    public static function findAllForIdsYear($ids, int $year): Collection
    {
        return self::where('year', $year)->whereIntegerInRaw('person_id', $ids)->get();
    }
}
