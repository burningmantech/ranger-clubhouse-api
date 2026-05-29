<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Lib\BulkUpload\Record;
use App\Lib\BulkUploader;

class PersonStatusHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->status;
            if ($commit) {
                $person->status = $action;
                $person->auditReason = $reason;
                BulkUploader::saveModel($person, $record);
            }
            $record->succeed([$oldValue, $action]);
        }
    }
}
