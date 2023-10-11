<?php

namespace App\Lib;

use App\Models\HandleReservation;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Validation\ValidationException;

class HandleReservationUpload
{
    /**
     * Perform a bulk upload of reserved handles
     *
     * Line formats are:
     * handle   - handle with no end date
     * handle,YYYY-MM-DD    - handle with end date
     * handle,YYYY-MM-DD,reason - handle with end date & reason
     * handle,,reason - handle with reason & no end date
     *
     * @param string $upload
     * @param string $type
     * @param string|null $expiresOn
     * @param string|null $reason
     * @param int|null $twiiYear
     * @param bool $commit
     * @return array
     * @throws ValidationException
     */

    public static function execute(string $upload, string $type, ?string $expiresOn, ?string $reason, ?int $twiiYear, bool $commit): array
    {
        $lines = explode("\n", $upload);

        $results = [];
        $errors = 0;

        $isTwii = $type == HandleReservation::TYPE_TWII_PERSON;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $columns = explode(',', $line);
            $handle = trim($columns[0]);
            $data = [
                'handle' => $handle,
                'reservation_type' => $type,
                'reason' => $reason,
                'expires_on' => $expiresOn,
                'twii_year' => $twiiYear,
            ];

            if (count($columns) >= 2 && !empty($columns[1])) {
                try {
                    $data['expires_on'] = (string)Carbon::parse($columns[1]);
                } catch (InvalidFormatException) {
                    $errors++;
                    $data['error'] = 'Invalid Date';
                    $data['status'] = 'error';
                    $results[] = $data;
                    continue;
                }
            }

            if (count($columns) >= 3) {
                $reason = trim($columns[2]);
                if (!empty($reason)) {
                    $data['reason'] = $reason;
                }
            }

            if (HandleReservation::handleTypeExists($handle, $type, $twiiYear)) {
                $data['error'] = $type == HandleReservation::TYPE_TWII_PERSON ? 'TWII Handle for year already exists.' : 'Handle and type already exists.';
                $data['status'] = 'error';
                $results[] = $data;
                $errors++;
                continue;
            }

            if (!$commit) {
                $data['status'] = 'success';
                if ($isTwii && empty($data['expires_on'])){
                    $data['expires_on'] = (current_year() + 2)."-09-15";
                }
                $results[] = $data;
                continue;
            }

            $record = new HandleReservation;
            $record->fill($data);
            $record->reservation_type = $type;
            if ($isTwii) {
                $record->twii_year = $twiiYear;
            }
            if ($record->save()) {
                $data['status'] = 'success';
            } else {
                $data['status'] = 'error';
                $data['error'] = '';
                foreach ($record->getErrors() as $column => $messages) {
                    $data['error'] .= "$column: " . implode(', ', $messages);
                }
                $errors++;
            }

            $results[] = $data;
        }

        return ['handles' => $results, 'errors' => $errors];
    }
}
