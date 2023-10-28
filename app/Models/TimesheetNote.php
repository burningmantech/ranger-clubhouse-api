<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimesheetNote extends ApiModel
{
    use HasFactory;

    protected $table = 'timesheet_note';

    const TYPE_USER = 'user';
    const TYPE_HQ_WORKER = 'hq-worker';
    const TYPE_WRANGLER = 'wrangler';
    const TYPE_ADMIN = 'admin';

    public bool $auditModel = true;

    // Notes are not directly updatable by the user.
    public $fillable = [];

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(Timesheet::class);
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
     * @param int $timesheetId
     * @param int|null $personId null indicates the note was left by a bot
     * @param ?string $note
     * @param string $type
     * @return void
     */

    public static function record(int $timesheetId, ?int $personId, ?string $note, string $type): void
    {
        if (!empty($note)) {
            $notes = trim($note);
        }

        if (empty($note)) {
            return;
        }


        self::insert([
            'timesheet_id' => $timesheetId,
            'create_person_id' => $personId,
            'note' => $note,
            'type' => $type,
            'created_at' => now(),
        ]);
    }
}
