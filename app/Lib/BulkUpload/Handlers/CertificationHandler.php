<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Lib\BulkUpload\Record;
use App\Lib\BulkUploader;
use App\Models\Certification;
use App\Models\PersonCertification;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class CertificationHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        $id = (int)str_replace('cert-', '', $action);
        $cert = Certification::find($id);
        if (!$cert) {
            throw new RuntimeException("Certification ID [$id] for bulk uploading cannot be found?!?");
        }

        $personIds = array_values(array_filter(array_map(
            fn (Record $r) => $r->person?->id,
            $records,
        )));

        $existingByPerson = PersonCertification::where('certification_id', $cert->id)
            ->whereIn('person_id', $personIds)
            ->get()
            ->keyBy('person_id');

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $record->succeed();

            [$issuedOn, $cardNumber, $trainedOn] = $this->parseDates($record);
            // parseDates may have flipped the record to failed
            if ($record->status === BulkUploader::STATUS_FAILED) {
                continue;
            }

            $pc = $existingByPerson->get($person->id);
            if ($pc) {
                $record->warn($this->existingDescription($cardNumber, $trainedOn, $issuedOn));
            }

            if (!$commit) {
                continue;
            }

            if (!$pc) {
                $pc = new PersonCertification();
                $pc->person_id = $person->id;
                $pc->certification_id = $cert->id;
                $pc->recorder_id = Auth::id();
            }

            if (!empty($cardNumber)) {
                $pc->card_number = $cardNumber;
            }
            if (!empty($trainedOn)) {
                $pc->trained_on = $trainedOn;
            }
            if (!empty($issuedOn)) {
                $pc->issued_on = $issuedOn;
            }

            $pc->auditReason = $reason;
            $pc->saveWithoutValidation();
            // Match historical behavior: in commit mode the row reports
            // SUCCESS even when an existing certification was updated. The
            // warning details (set above) remain on the record.
            $record->status = BulkUploader::STATUS_SUCCESS;
        }
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function parseDates(Record $record): array
    {
        $data = $record->data;
        $count = count($data);
        $cardNumber = null;
        $trainedOn = null;
        $issuedOn = null;

        switch ($count) {
            case 3:
                $trainedOn = $data[2];
                if (!BulkUploader::dateIsValid($trainedOn)) {
                    $record->fail('Invalid trained on date');
                }
                // fall-through
            case 2:
                $cardNumber = $data[1];
                // fall-through
            case 1:
                $issuedOn = $data[0];
                if (!BulkUploader::dateIsValid($issuedOn)) {
                    $record->fail('Invalid issued on date');
                }
                break;
        }

        return [$issuedOn, $cardNumber, $trainedOn];
    }

    private function existingDescription(?string $cardNumber, ?string $trainedOn, ?string $issuedOn): string
    {
        $fields = [];
        if (!empty($cardNumber)) {
            $fields[] = 'Card number';
        }
        if (!empty($trainedOn)) {
            $fields[] = 'Trained on date';
        }
        if (!empty($issuedOn)) {
            $fields[] = 'Issued on date';
        }

        if (empty($fields)) {
            return 'Certification already exists. No record fields will be updated.';
        }

        return 'Certification already exists. ' . implode(', ', $fields) . ' will be updated.';
    }
}
