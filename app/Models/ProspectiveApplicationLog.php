<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @property int prospective_application_id
 * @property string action
 * @property ?int person_id
 * @property mixed data
 * @property Carbon created_at
 */
class ProspectiveApplicationLog extends ApiModel
{

    protected $table = 'prospective_application_log';

    // Application was imported from Salesforce
    const string ACTION_CREATED = 'created';
    const string ACTION_EMAILED = 'emailed';
    const string ACTION_IMPORTED = 'imported';
    const string ACTION_UPDATED = 'updated';

    protected $casts = [
        'data' => 'array',
    ];

    protected $appends = [
        'meta'
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(ProspectiveApplication::class);
    }

    public static function record(int $applicationId, string $action, mixed $data = null): void
    {
        $log = new self;
        $log->prospective_application_id = $applicationId;
        $log->action = $action;
        $log->person_id = Auth::id();
        $log->created_at = now();

        if (!empty($data)) {
            $log->data = $data;
        }

        $log->save();
    }

    public function getMetaAttribute(): mixed
    {
        if ($this->action != self::ACTION_UPDATED) {
            return null;
        }

        $assigned = $this->data['assigned_person_id'] ?? null;
        if (!$assigned) {
            return null;
        }

        if ($assigned[0]) {
            $from = DB::table('person')->where('id', $assigned[0])->value('callsign');
        } else {
            $from = null;
        }

        if ($assigned[1]) {
            $to = DB::table('person')->where('id', $assigned[1])->value('callsign');
        } else {
            $to = null;
        }

        return ['assigned_person' => [$from, $to]];
    }
}
