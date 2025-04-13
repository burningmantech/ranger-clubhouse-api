<?php

namespace App\Models;

use App\Lib\YearsManagement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PersonSlot extends ApiModel
{
    protected $table = 'person_slot';

    protected $fillable = [
        'person_id',
        'slot_id'
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime'
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public static function boot(): void
    {
        parent::boot();

        self::created(function ($model) {
            YearsManagement::updateSignupYears($model->person_id);
        });

        self::deleted(function ($model) {
            YearsManagement::updateSignupYears($model->person_id);
        });
    }

    /**
     * Is a person signed up for a given slot?
     *
     * @param int $personId
     * @param int $slotId
     * @return bool
     */

    public static function haveSlot(int $personId, int $slotId): bool
    {
        return self::where('person_id', $personId)->where('slot_id', $slotId)->exists();
    }

    /**
     * Delete all slot sign-ups. Used by slot deletion.
     *
     * @param int $slotId
     */

    public static function deleteForSlot(int $slotId): void
    {
        $rows = self::where('slot_id', $slotId)->get();
        $user = Auth::user();

        foreach ($rows as $row) {
            ActionLog::record($user, 'person-slot-remove', 'slot deletion', ['slot_id' => $slotId], $row->person_id);
        }

        self::where('slot_id', $slotId)->delete();
    }

    /**
     * Is the person signed up for a Motor Vehicle Request eligible shift?
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */
    public static function hasMVREligibleSignups(int $personId, int $year): bool
    {
        return self::commonVehicleEligibility($personId, $year, 'mvr_eligible')
            || self::commonVehicleEligibility($personId, $year, 'mvr_signup_eligible');
    }

    /**
     * Retrieve all MVR signup-eligible shifts for a person
     * @param int $personId
     * @param int $year
     * @return array
     */

    public static function retrieveMVRSignups(int $personId, int $year): array
    {
        return self::select('slot.id', 'slot.begins', 'slot.position_id', 'position.title as position_title')
            ->join('slot', 'slot.id', 'person_slot.slot_id')
            ->join('position', 'position.id', 'slot.position_id')
            ->where('person_slot.person_id', $personId)
            ->where('slot.begins_year', $year)
            ->where('slot.active', true)
            ->where('position.mvr_signup_eligible', true)
            ->orderBy('slot.begins')
            ->get()
            ->toArray();
    }

    /**
     * Is the person signed up for a Personal Vehicle Request eligible shift?
     *
     * @param int $personId
     * @param int $year
     * @return bool
     */

    public static function hasPVREligibleSignups(int $personId, int $year): bool
    {
        return self::commonVehicleEligibility($personId, $year, 'pvr_eligible');
    }

    /**
     * Does the person have a vehicle (MVR or PVR) qualification?
     *
     * @param int $personId
     * @param int $year
     * @param string $column
     * @return bool
     */

    public static function commonVehicleEligibility(int $personId, int $year, string $column): bool
    {
        return DB::table('position')
            ->join('slot', 'slot.position_id', 'position.id')
            ->join('person_slot', 'person_slot.slot_id', 'slot.id')
            ->where('position.active', true)
            ->where('position.' . $column, true)
            ->where('slot.active', true)
            ->where('slot.begins_year', $year)
            ->where('person_slot.person_id', $personId)
            ->exists();
    }
}
