<?php

namespace App\Lib;

use App\Models\AccessDocument;
use App\Models\Bmid;
use App\Models\BmidExport;

use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;

use ZipArchive;

use Exception;
use RuntimeException;

class MarcatoExport
{
    const BUDGET_CODE = 'Rangers 660';
    const CSV_HEADERS = [
        'Name',
        'Email',
        'Company',
        'Position',
        'Notes',
        'Playa Name/Radio Handle',
        'Arrival Date',
        'Shower Access For Entire Event - Shower - Tue9',
        'Period Catering Bundle - BMID Catering  - Pre-Event',
        'Period Catering Bundle - BMID Catering  - During Event',
        'Period Catering Bundle - BMID Catering  - Post Event',
        'title2',
        'title3'
    ];

    public string|null $exportFile = null;
    public string|null $photoZip = null;
    public string|null $csvFile = null;


    /**
     * Export BMIDs for upload into Marcato.
     *
     * The following actions are taken:
     *
     * 1. A Zip archive is created containing
     *    - A CSV file with BMIDs (name, titles, showers, meals)
     *    - A ZIP Archive contains the BMID photos
     * 2. The BMIDs are marked submitted.
     * 3. Provision Items are marked submitted.
     * 4. The newly created archive is uploaded to the BMID export store (S3 or local filesystem)
     *
     * @param $bmids - BMIDs to export
     * @param $batchInfo - The export batch information
     * @return string - The url to the newly created exported file
     * @throws Exception
     */

    public static function export($bmids, string|null $batchInfo): string
    {
        $marcato = new self($bmids, $batchInfo);

        foreach ($marcato->bmids as $bmid) {
            $bmid->load('person.person_photo');
            if (!$bmid->person->person_photo) {
                throw new \InvalidArgumentException("{$bmid->person->callsign} does not have a photo record");
            }

            if (!$bmid->person->person_photo->imageExists()) {
                throw new \InvalidArgumentException("{$bmid->person->callsign} has photo record but image file is missing.");
            }
        }

        try {
            $marcato->createExportFile();
        } catch (Exception $e) {
            $marcato->teardown();
            throw $e;
        }

        $marcato->teardown();
        $marcato->markSubmitted();

        $file = $marcato->exportFile;

        $export = new BmidExport;
        $export->person_id = Auth::id();
        $export->batch_info = $batchInfo;
        $export->storeExport(basename($file), file_get_contents($file));
        $export->person_ids = $marcato->bmids->pluck('person_id')->toArray();
        $export->created_at = now();
        $export->save();

        return $export->filename_url;
    }

    private function __construct(public $bmids, public $batchInfo)
    {
        $this->datestamp = date('m-d-Y_H:m');
    }

    /**
     * Cleanup any temporary files created
     */

    public function teardown()
    {
        if ($this->csvFile) {
            unlink($this->csvFile);
        }

        if ($this->photoZip) {
            unlink($this->photoZip);
        }
    }

    /**
     * Attempt to create a temporary file.
     *
     * @param string $name
     * @return string
     */

    public static function tempFile(string $name): string
    {
        $file = tempnam(sys_get_temp_dir(), $name);
        if ($file === false) {
            throw new \RuntimeException("Failed to create temporary file [$name]");
        }
        return $file;
    }

    /**
     * Create the main ZIP archive which will be downloaded by the user.
     */
    public function createExportFile()
    {
        $this->csvFile = self::tempFile('export.csv');
        $this->photoZip = self::tempFile('photos.zip');
        $this->exportFile = sys_get_temp_dir() . '/' . $this->datestamp . '-marcato.zip';

        $this->createCSV();
        $this->createPhotoZipfile();

        $zip = new ZipArchive();
        $result = $zip->open($this->exportFile, ZipArchive::CREATE);
        if ($result !== true) {
            throw new \RuntimeException("Failed to create zip archive result=[$result]");
        }
        $zip->addFile($this->csvFile, $this->datestamp . '.csv');
        $zip->addFile($this->photoZip, $this->datestamp . '_ranger_photos.zip');
        $zip->close();
    }


    /**
     * Create the photo zip file. Each photo is downloaded from the photo store, and then
     * added to the archive.
     *
     */
    public function createPhotoZipfile()
    {
        $zip = new ZipArchive();
        $result = $zip->open($this->photoZip, ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException("Failed to create zip file [{$this->photoZip}] result=[$result]");
        }

        foreach ($this->bmids as $bmid) {
            // pull down each file
            $data = $bmid->person->person_photo->readImage();
            $image = self::buildPhotoName($bmid->person);
            $result = $zip->addFromString($image, $data);
            if ($result !== true) {
                throw new RuntimeException("Failed to add photo file [$image] result=[$result]");
            }
            // Force garbage collection.
            unset($data);
            gc_collect_cycles();
        }
        $zip->close();

        unset($zip);
        gc_collect_cycles();
    }

    /**
     * Create the CSV. Each row represents one BMID with callsign, real name,
     * titles, showers, and meals.
     */

    public function createCSV()
    {
        $fh = fopen($this->csvFile, 'w');
        if ($fh === false) {
            throw new RuntimeException("Failed to create CSV file [$this->csvFile]");
        }

        self::writeCSV($fh, self::CSV_HEADERS);
        foreach ($this->bmids as $bmid) {
            self::writeCSV($fh, self::buildExportRow($bmid));
        }

        fclose($fh);
    }

    /**
     * Write out a CSV row, quoting string columns
     * (Why the f**k doesn't fputcsv() do this!?!?)
     *
     * @param $fh
     * @param $columns
     */

    public static function writeCSV($fh, array $columns)
    {
        $quoted = array_map(function ($c) {
            if (empty($c)) {
                return '';
            }
            return is_string($c) ?  '"' . str_replace('"', '""', $c) . '"' : $c;
        }, $columns);
        fwrite($fh, implode(',', $quoted).PHP_EOL);
    }

    /**
     * Build an array used to represent a BMID in the CSV file.
     * @param $bmid
     * @return array
     */

    public static function buildExportRow($bmid): array
    {
        $person = $bmid->person;

        if ($bmid->access_any_time) {
            $arrivalDate = date('07/15/Y');
        } else {
            $arrivalDate = Carbon::parse($bmid->access_date)->format('m/d/Y');
        }

        $meals = $bmid->buildMealsMatrix();

        return [
            $person->first_name . ' ' . $person->last_name,
            $person->email,
            self::BUDGET_CODE,
            $bmid->title1 ?? '',
            $person->callsign,
            '', // Supervisor - pulled from Salesforce, intentionally left blank
            $arrivalDate,
            self::buildPhotoName($person),
            ($bmid->showers || $bmid->want_showers) ? '100' : '',
            isset($meals[Bmid::MEALS_PRE]) ? 1 : 0,
            isset($meals[Bmid::MEALS_EVENT]) ? 1 : 0,
            isset($meals[Bmid::MEALS_POST]) ? 1 : 0,
            $bmid->title2 ?? '',
            $bmid->title3 ?? ''
        ];
    }

    /**
     * Build the photo filename for the photo zip archive.
     *
     * "FIRST LAST"_rangers.jpg  (note: name is uppercased with a space)
     *
     * @param $person
     * @return string
     */

    public static function buildPhotoName($person): string
    {
        return strtoupper($person->first_name . ' ' . $person->last_name) . '_rangers.jpg';
    }

    /**
     * Mark each BMID as submitted, note what showers & meals were set,
     * and mark any provision items as submitted.
     */

    public function markSubmitted()
    {
        $user = Auth::user()->callsign;
        $uploadDate = date('n/j/y G:i:s');
        $batchInfo = $this->batchInfo ?? '';

        foreach ($this->bmids as $bmid) {
            $bmid->status = Bmid::SUBMITTED;

            /*
             * Make a note of what provisions were set
             */

            if ($bmid->want_showers) {
                $showers = '[showers claimed]';
            } else if ($bmid->showers) {
                $showers = '[showers set]';
            } else {
                $showers = '[showers none]';
            }

            $meals = [];
            if (!empty($bmid->meals)) {
                $meals[] = $bmid->meals . ' set';
            }
            if (!empty($bmid->want_meals)) {
                $meals[] = $bmid->want_meals . ' claimed';
            }
            if (empty($meals)) {
                $meals[] = 'none';
            }

            /*
             * Figure out which provisions are to be marked as submitted.
             *
             * Note: Meals and showers are not allowed to be banked in the case were the
             * person earned them yet their position (Council, OOD, Supervisor, etc.) comes
             * with a provisions package.
             */

            $items = [];
            if ($bmid->want_showers || $bmid->showers) {
                $items[] = AccessDocument::WET_SPOT;
            }

            if ($bmid->meals == Bmid::MEALS_ALL) {
                $items[] = AccessDocument::ALL_EAT_PASS;
                $items[] = AccessDocument::EVENT_EAT_PASS;
            } else {
                // Currently only two meal provision types, All Eat, and Event Week
                if (!empty($bmid->want_meals)) {
                    $items[] = ($bmid->want_meals == Bmid::MEALS_ALL) ? AccessDocument::ALL_EAT_PASS : AccessDocument::EVENT_EAT_PASS;
                }

                // Was person given event week meals?
                if (stripos($bmid->meals, 'event') !== false) {
                    $items[] = AccessDocument::EVENT_EAT_PASS;
                }
            }

            if (!empty($items)) {
                $items = array_unique($items);
                AccessDocument::markSubmittedForBMID($bmid->person_id, $items);
            }

            $meals = '[meals ' . implode(',', $meals) . ']';
            $bmid->notes = "$uploadDate $user: Exported $meals $showers\n$bmid->notes";
            $bmid->auditReason = 'exported to print';
            $bmid->batch = $batchInfo;
            $bmid->saveWithoutValidation();
        }
    }

}