<?php

namespace App\Models;

use App\Attributes\BlankIfEmptyAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PersonPog extends ApiModel
{
    protected $table = 'person_pog';
    protected bool $auditModel = true;
    public $timestamps = true;

    const string POG_SHOWER = 'shower';
    const string POG_MEAL = 'meal';
    const string POG_HALF_MEAL = 'half-meal';

    const string STATUS_ISSUED = 'issued';
    const string STATUS_CANCELLED = 'cancelled';
    const string STATUS_REDEEMED = 'redeemed';

    const array RELATIONSHIPS = [
        'issued_by:id,callsign',
        'timesheet:id,position_id,on_duty,off_duty'
    ];

    protected $fillable = [
        'issued_at',
        'notes',
        'person_id',
        'pog',
        'status',
        'timesheet_id',
    ];

    protected $attributes = [
        'notes' => '',
        'status' => self::STATUS_ISSUED,
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'issued_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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

    public static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (empty($model->issued_at)) {
                $model->issued_at = now();
            }
        });
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

        $sql = self::with(self::RELATIONSHIPS)->orderBy('created_at', 'desc');
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
     */

    public function notes(): Attribute
    {
        return BlankIfEmptyAttribute::make();
    }
}
