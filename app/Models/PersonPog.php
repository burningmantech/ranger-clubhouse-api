<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PersonPog extends ApiModel
{
    protected $table = 'person_pog';
    protected $auditModel = true;
    public $timestamps = true;

    const POG_SHOWER = 'shower';
    const POG_MEAL = 'meal';
    const POG_HALF_MEAL = 'half-meal';

    const STATUS_ISSUED = 'issued';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REDEEMED = 'redeemed';

    const RELATIONSHIPS = ['issued_by:id,callsign', 'timesheet:id,position_id,on_duty,off_duty'];

    protected $fillable = [
        'person_id',
        'timesheet_id',
        'pog',
        'status',
        'notes'
    ];

    protected $attributes = [
        'notes' => '',
        'status' => self::STATUS_ISSUED,
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function issued_by(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function save($options = []): bool
    {
        if ($this->isDirty('timesheet_id')) {
            $timesheetId = $this->timesheet_id;
            if (!empty($timesheetId)) {
                $entry = DB::table('timesheet')->where('id', $timesheetId)->first();
                if ($entry === null) {
                    $this->addError('timesheet_id', 'Timesheet does not exist');
                    return false;
                }

                if ($entry->person_id != $this->person_id) {
                    $this->addError('timesheet_id', 'Timesheet does not belong to person');
                    return false;
                }
            }
        }

        return parent::save($options);
    }

    public function loadRelationships()
    {
        $this->load(self::RELATIONSHIPS);
    }

    /**
     * Find all the pogs given to a person
     *
     * @param array $query
     * @return Collection
     */

    public static function findForQuery(array $query): Collection
    {
        $personId = $query['person_id'] ?? null;
        $pog = $query['pog'] ?? null;
        $year = $query['year'] ?? null;

        $sql = self::with(self::RELATIONSHIPS);
        if ($personId) {
            $sql->where('person_id', $personId);
        }

        if ($pog) {
            $sql->where('pog', $pog);
        }

        if ($year) {
            $sql->whereYear('created_at', $year);
        }
        return $sql->get();
    }

    /**
     * Set the notes
     *
     * @param string|null $value
     * @return void
     */

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = empty($value) ? '' : $value;
    }
}
