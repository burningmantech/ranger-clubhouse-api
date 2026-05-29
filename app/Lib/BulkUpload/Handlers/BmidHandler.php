<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Exceptions\UnacceptableConditionException;
use App\Lib\BulkUpload\Record;
use App\Lib\BulkUploader;
use App\Models\Bmid;

class BmidHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        if ($action !== 'bmidsubmitted') {
            throw new UnacceptableConditionException("Unknown BMID action [$action]");
        }

        $year = current_year();

        $personIds = array_values(array_filter(array_map(
            fn (Record $r) => $r->person?->id,
            $records,
        )));

        $bmidsByPerson = collect(Bmid::findForPersonIds($year, $personIds))->keyBy('person_id');

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $bmid = $bmidsByPerson->get($person->id) ?? Bmid::findForPersonManage($person->id, $year);

            if ($bmid->status !== Bmid::ISSUES && $bmid->status !== Bmid::READY_TO_PRINT) {
                $record->fail("BMID has status [{$bmid->status}] and cannot be submitted");
                continue;
            }

            $oldStatus = $bmid->status;
            $bmid->status = Bmid::SUBMITTED;
            $bmid->auditReason = $reason;

            if ($commit && !BulkUploader::saveModel($bmid, $record)) {
                continue;
            }

            $record->succeed([$oldStatus, Bmid::SUBMITTED]);
        }
    }
}
