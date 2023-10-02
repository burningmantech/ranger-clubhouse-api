<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Do not use ApiModel, do not want to audit the audit table.

class AccessDocumentChanges extends Model
{
    use HasFactory;

    protected $table = 'access_document_changes';
    public $timestamps = false;

    // Allow all columns to be filed -- model is not directly accessible by the client.
    protected $guarded = [];

    public $casts = [
        'changes' => 'array',
        'created_at' => 'datetime'
    ];

    const OP_MODIFY = 'modify';
    const OP_CREATE = 'create';
    const OP_DELETE = 'delete';

    public function changer_person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Log changes to an access document
     *
     * @param $record
     * @param $personId
     * @param $changes
     * @param string $op
     * @return void
     */

    public static function log($record, $personId, $changes, string $op = self::OP_MODIFY): void
    {
        if (is_integer($record)) {
            $id = $record;
        } else {
            $id = $record->id;
        }

        $id ??= 0;

        unset($changes['comments']); // Don't track comment changes

        if (empty($changes)) {
            return;
        }

        $row = new AccessDocumentChanges([
            'table_name' => 'access_document',
            'record_id' => $id,
            'operation' => $op,
            'changes' => $changes,
            'changer_person_id' => $personId
        ]);

        $row->save();
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param DateTimeInterface $date
     * @return string
     */

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
