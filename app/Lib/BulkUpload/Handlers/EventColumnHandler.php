<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkUpload\Record;
use App\Lib\BulkUploader;
use App\Models\PersonEvent;

class EventColumnHandler implements Handler
{
    /**
     * PersonEvent boolean columns this handler may toggle. Whitelisted
     * for the same reason as PersonColumnHandler::ALLOWED_COLUMNS.
     */
    private const array ALLOWED_COLUMNS = [
        'org_vehicle_insurance',
        'signed_motorpool_agreement',
        'may_request_stickers',
        'sandman_affidavit',
        'mvr_eligible',
    ];

    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        if (!in_array($action, self::ALLOWED_COLUMNS, true)) {
            throw new UnacceptableConditionException("Unknown event column action [$action]");
        }

        $year = current_year();
        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $event = PersonEvent::firstOrNewForPersonYear($person->id, $year);
            $oldValue = $event->$action;
            $event->$action = 1;
            $event->auditReason = $reason;

            $record->succeed();

            if ($commit && !BulkUploader::saveModel($event, $record)) {
                continue;
            }
            $record->changes = [$oldValue, 1];
        }
    }
}
