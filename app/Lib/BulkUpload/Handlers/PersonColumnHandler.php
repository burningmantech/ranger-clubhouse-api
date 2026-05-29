<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkUpload\Record;
use App\Lib\BulkUploader;

class PersonColumnHandler implements Handler
{
    /**
     * Person boolean columns this handler is allowed to set. Restricting
     * to a whitelist prevents a stray ACTIONS-map entry from turning into
     * a write-to-arbitrary-column primitive.
     */
    private const array ALLOWED_COLUMNS = ['vintage'];

    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        if (!in_array($action, self::ALLOWED_COLUMNS, true)) {
            throw new UnacceptableConditionException("Unknown person column action [$action]");
        }

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $oldValue = $person->$action;
            $person->$action = 1;
            $record->succeed();

            if ($commit && !BulkUploader::saveModel($person, $record)) {
                continue;
            }
            $record->changes = [$oldValue, 1];
        }
    }
}
