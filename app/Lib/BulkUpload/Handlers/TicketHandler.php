<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Lib\BulkUpload\Record;
use App\Lib\BulkUpload\TicketTypeCode;
use App\Lib\BulkUploader;
use App\Models\AccessDocument;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;

class TicketHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        [$defaultSourceYear, $defaultExpiryYear] = BulkUploader::defaultYears(false);
        $year = current_year();
        $uploaderCallsign = Auth::user()?->callsign ?? 'unknown';

        $sapsByPerson = AccessDocument::where(function ($w) {
            // SAPs, or Staff Credentials with an access specification.
            $w->where('type', AccessDocument::WAP);
            $w->orWhere(function ($sc) {
                $sc->where('type', AccessDocument::STAFF_CREDENTIAL);
                $sc->where(function ($access) {
                    $access->where('access_any_time', true);
                    $access->orWhereNotNull('access_date');
                });
            });
        })->whereIn('status', [AccessDocument::QUALIFIED, AccessDocument::CLAIMED, AccessDocument::SUBMITTED])
            ->get()
            ->groupBy('person_id');

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $data = $record->data;
            if (empty($data)) {
                $record->fail('missing ticket type');
                continue;
            }

            $code = TicketTypeCode::tryFromInput($data[0]);
            if ($code === null) {
                $record->fail("Unknown ticket type [{$data[0]}]");
                continue;
            }

            $type = $code->accessDocumentType();
            $sourceYear = $code->sourceYearOverride() ?? $defaultSourceYear;
            $expiryYear = $code->acceptsExpiryYear() ? $defaultExpiryYear : $year;
            $accessDate = null;
            $accessDateCleaned = null;

            $nextInd = 1;
            $dataCount = count($data);

            if ($code->acceptsAccessDate() && $dataCount > $nextInd) {
                $accessDate = trim($data[$nextInd++]);
            }

            if (in_array($type, AccessDocument::TICKET_TYPES) && $dataCount > $nextInd) {
                if (!BulkUploader::checkYearRange($sourceYear, $data[$nextInd++], $record, true)) {
                    continue;
                }
            }

            if ($code->acceptsExpiryYear() && $dataCount > $nextInd) {
                if (!BulkUploader::checkYearRange($expiryYear, $data[$nextInd++], $record, false)) {
                    continue;
                }
            }

            if ($dataCount !== $nextInd) {
                $record->fail("Unexpected additional data for $type. See the required line format");
                continue;
            }

            if ($expiryYear < $sourceYear) {
                $record->fail("Source year [$sourceYear] must not be after expiry year [$expiryYear]");
                continue;
            }

            if ($accessDate !== null) {
                $accessDateCleaned = $this->parseAccessDate($accessDate, $year, $record);
                if ($accessDateCleaned === null) {
                    continue;
                }
            }

            if ($type === AccessDocument::WAP && !$this->wapDoesNotShortenAccess($sapsByPerson, $person->id, $accessDateCleaned, $record)) {
                continue;
            }

            $uploadDate = date('n/j/y G:i:s');
            $ad = new AccessDocument([
                'person_id' => $person->id,
                'type' => $type,
                'source_year' => $sourceYear,
                'expiry_date' => $expiryYear,
                'comments' => "$uploadDate {$uploaderCallsign}: $reason",
                'status' => AccessDocument::QUALIFIED,
            ]);

            if ($accessDateCleaned !== null) {
                $ad->access_date = $accessDateCleaned;
            }

            $record->succeed();
            if ($commit) {
                BulkUploader::saveModel($ad, $record);
            }
        }
    }

    private function parseAccessDate(string $accessDate, int $year, Record $record): ?Carbon
    {
        // A date can't reasonably be specified in four or fewer characters. This is a
        // guard against someone putting in a year rather than a date; Carbon happily
        // misinterprets "YYYY" as today at time YY:YY:00.
        if (strlen($accessDate) <= 4) {
            $record->fail("Access date is invalid. Try YYYY-MM-DD format. [$accessDate]");
            return null;
        }

        try {
            $cleaned = Carbon::parse($accessDate);
        } catch (Exception) {
            $record->fail("Access date is invalid. Try YYYY-MM-DD format. [$accessDate]");
            return null;
        }

        if ($cleaned->year < $year) {
            $record->fail("Access date is before this year $year [$accessDate]");
            return null;
        }

        return $cleaned;
    }

    private function wapDoesNotShortenAccess(
        $sapsByPerson,
        int $personId,
        ?Carbon $accessDateCleaned,
        Record $record,
    ): bool {
        $ads = $sapsByPerson->get($personId);
        if (!$ads) {
            return true;
        }

        $sap = AccessDocument::wapCandidate($ads);
        if (!$sap) {
            return true;
        }

        if ($sap->type === AccessDocument::STAFF_CREDENTIAL) {
            if ($sap->access_any_time) {
                $record->fail("Staff Credential RAD-{$sap->id} status {$sap->status} exists with any time access");
                return false;
            }
            if ($accessDateCleaned !== null && $sap->access_date->lt($accessDateCleaned)) {
                $record->fail("Staff Credential RAD-{$sap->id} status {$sap->status} has an early access date of " . ((string)$sap->access_date));
                return false;
            }
            return true;
        }

        if ($accessDateCleaned !== null && $sap->access_date->lt($accessDateCleaned)) {
            $record->fail("SAP RAD-{$sap->id} status {$sap->status} has an early access date of " . ((string)$sap->access_date));
            return false;
        }

        return true;
    }
}
