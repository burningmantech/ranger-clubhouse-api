<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectiveApplicationNote extends ApiModel
{
    protected $table = 'prospective_application_note';

    const string TYPE_RRN = 'rrn';
    const string TYPE_VC = 'vc';
    const string TYPE_VC_FLAG = 'vc-flag';
    const string TYPE_VC_COMMENT = 'vc-comment';

    protected $guarded = [];

    public static function boot(): void
    {
        parent::boot();

        self::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function prospective_application(): BelongsTo
    {
        return $this->belongsTo(ProspectiveApplication::class);
    }

    public static function findNoteForApplication($applicationId, $noteId) : ?ProspectiveApplicationNote
    {
        return self::where('prospective_application_id', $applicationId)
            ->where('id', $noteId)
            ->firstOrFail();
    }
}
