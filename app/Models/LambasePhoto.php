<?php

namespace App\Models;

/*
 * Purely for conversion and archival purposes
 */

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LambasePhoto extends ApiModel
{
    protected $table = 'lambase_photo';

    protected function casts(): array
    {
        return [
            'lambase_date' => 'datetime'
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
