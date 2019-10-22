<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Do not use ApiModel, do not want to audit the audit table.

class AccessDocumentChanges extends Model
{
    protected $table = 'access_document_changes';
    public $timestamps = false;

    protected $fillable = [
        'table_name',
        'record_id',
        'operation',
        'changes',
        'changer_person_id',
    ];

    public static function log($record, $personId, $changes, $op='modify') {
        if (is_integer($record)) {
            $id = $record;
        } else {
            $id = $record->id;
        }

        $row = new AccessDocumentChanges([
            'table_name'    => 'access_document',
            'record_id'     => $id,
            'operation'     => $op,
            'changes'       => json_encode($changes),
            'changer_person_id' => $personId
        ]);

        $row->save();
    }
}
