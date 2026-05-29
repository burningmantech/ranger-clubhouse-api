<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Lib\BulkUpload\Record;
use App\Models\AccessDocument;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class SapHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        $year = current_year();
        [$low, $high] = $this->validDayRange();

        $personIds = array_values(array_filter(array_map(
            fn (Record $r) => $r->person?->id,
            $records,
        )));
        $wapsByPerson = AccessDocument::findWAPForPersonIds($personIds);

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            if (empty($record->data)) {
                $record->fail('missing access type');
                continue;
            }

            $accessDate = trim($record->data[0]);
            $anytime = strtolower($accessDate) === 'any';
            $accessDateCleaned = null;

            if (!$anytime) {
                try {
                    $accessDateCleaned = Carbon::parse($accessDate);
                } catch (InvalidFormatException) {
                    $record->fail("Invalid date [$accessDate]");
                    continue;
                }

                if ($accessDateCleaned->year !== $year
                    || $accessDateCleaned->month !== 8
                    || $accessDateCleaned->day < $low
                    || $accessDateCleaned->day > $high
                ) {
                    $record->fail("Date is outside of $year-08-$low and 08-$high");
                    continue;
                }
            }

            $wap = $wapsByPerson[$person->id] ?? null;

            if ($wap === null) {
                $record->fail('No SAP access document could be found');
                continue;
            }

            if ($wap->status === AccessDocument::SUBMITTED) {
                $record->fail('SAP has already been submitted');
                continue;
            }

            if (!$anytime) {
                if ($wap->type === AccessDocument::STAFF_CREDENTIAL && $wap->access_any_time) {
                    $record->fail("Staff Credential RAD-{$wap->id} status {$wap->status} will be cleared of access any time.");
                    continue;
                }

                if ($wap->access_date?->lt($accessDateCleaned)) {
                    $record->fail("SAP RAD-{$wap->id} access date {$wap->access_date->format('Y-m-d')} would be replaced with a later access date {$accessDateCleaned->format('Y-m-d')}");
                    continue;
                }
            }

            if ($commit) {
                AccessDocument::updateSAPsForPerson(
                    $person->id,
                    $anytime ? null : $accessDateCleaned,
                    $anytime,
                    'set via bulk uploader',
                );
            }

            $record->succeed();
        }
    }

    /**
     * @return array{int, int}
     */
    private function validDayRange(): array
    {
        $wapDate = setting('TAS_SAPDateRange', true);
        if (empty($wapDate)) {
            return [5, 26];
        }
        [$low, $high] = explode('-', $wapDate);
        return [(int)$low, (int)$high];
    }
}
