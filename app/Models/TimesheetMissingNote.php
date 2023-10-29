<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetMissingNote extends ApiModel
{
    use HasFactory;

    protected $table = 'timesheet_missing_note';
    protected bool $auditModel = true;

    const TYPE_USER = 'user';
    const TYPE_HQ_WORKER = 'hq-worker';
    const TYPE_WRANGLER = 'wrangler';
    const TYPE_ADMIN = 'admin';


    // Notes are not directly updatable by the user.
    public $fillable = [];

    public function timesheet_missing(): BelongsTo
    {
        return $this->belongsTo(TimesheetMissing::class);
    }

    public function create_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Record a timesheet note
     *
     * @param int $timesheetMissingId
     * @param int|null $personId null indicates the note was left by a bot
     * @param ?string $note
     * @param string $type
     * @return void
     */

    public static function record(int $timesheetMissingId, ?int $personId, ?string $note, string $type): void
    {
        if (!empty($note)) {
            $notes = trim($note);
        }

        if (empty($note)) {
            return;
        }


        self::insert([
            'timesheet_missing_id' => $timesheetMissingId,
            'create_person_id' => $personId,
            'note' => $note,
            'type' => $type,
            'created_at' => now(),
        ]);
    }
}
