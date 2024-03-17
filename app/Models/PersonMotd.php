<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonMotd extends ApiModel
{
    protected $table = 'person_motd';
    protected $guarded = [];    // table is not directly accessible, allow anything

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function motd(): BelongsTo
    {
        return $this->belongsTo(Motd::class);
    }

    public static function markAsRead(int $personId, int $motdId): void
    {
        self::updateOrCreate([
            'person_id' => $personId,
            'motd_id' => $motdId
        ], [
            'read_at' => now()
        ]);
    }
}
