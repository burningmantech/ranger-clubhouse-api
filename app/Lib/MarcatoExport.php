<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Models\Bmid;
use App\Models\BmidExport;
use App\Models\Provision;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use ZipArchive;

class MarcatoExport
{
    const string BUDGET_CODE = 'Rangers 660';

    const CSV_HEADERS = [
        'Name',
        'Email',
        'Playa Name/Radio Handle',
        'Company',
        'Position',
        'Supervisor',
        'Arrival Date',
        'Notes',
        'Shower Access For Entire Event - Shower - Wed7',
        'Period Catering Bundle - BMID Catering  - Pre-Event',
        'Period Catering Bundle - BMID Catering  - During Event',
        'Period Catering Bundle - BMID Catering  - Post Event',
        'title2',
        'title3'
    ];

    public string|null $exportFile = null;
    public string|null $photoZip = null;
    public string|null $csvFile = null;
    public string|null $datestamp = null;

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
            $photo = $bmid->person->approvedPhoto();
            if (!$photo) {
                throw new UnacceptableConditionException("{$bmid->person->callsign} does not have a photo record");
            }

            if (!$photo->imageExists()) {
                throw new UnacceptableConditionException("{$bmid->person->callsign} has photo record but image file is missing.");
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
        $this->datestamp = date('m-d-Y_H:m:s');
    }

    /**
     * Cleanup any temporary files created
     */

    public function teardown()
    {
        if ($this->csvFile) {
            unlink($this->csvFile);
            $this->csvFile = null;
        }

        if ($this->photoZip) {
            unlink($this->photoZip);
            $this->photoZip = null;
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
            throw new RuntimeException("Failed to create temporary file [$name]");
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
        $result = $zip->open($this->exportFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException("Failed to create zip archive result=[$result]");
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
    public function createPhotoZipfile(): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($this->photoZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException("Failed to create zip file [{$this->photoZip}] result=[$result]");
        }

        foreach ($this->bmids as $bmid) {
            // pull down each file
            $data = $bmid->person->approvedPhoto()->readImage();
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

    public function createCSV(): void
    {
        $fh = fopen($this->csvFile, 'w');
        if ($fh === false) {
            throw new RuntimeException("Failed to create CSV file [$this->csvFile]");
        }

        CSV::write($fh, self::CSV_HEADERS);
        foreach ($this->bmids as $bmid) {
            CSV::write($fh, self::buildExportRow($bmid));
        }

        fclose($fh);
    }


    /**
     * Build an array used to represent a BMID in the CSV file.
     * @param Bmid $bmid
     * @return array
     */

    public static function buildExportRow(Bmid $bmid): array
    {
        $person = $bmid->person;

        if ($bmid->access_any_time) {
            $arrivalDate = date('07/15/Y');
        } else {
            $arrivalDate = Carbon::parse($bmid->access_date)->format('m/d/Y');
        }

        $meals = $bmid->meals_granted;
        return [
            $person->first_name . ' ' . $person->last_name,
            $person->email,
            $person->callsign,
            self::BUDGET_CODE,
            $bmid->title1 ?? '',
            '',                     // Supervisor is not required.
            $arrivalDate,
            self::buildPhotoName($person),
            $bmid->showers_granted ? '100' : '',
            $meals['pre'] ? 1 : 0,
            $meals['event'] ? 1 : 0,
            $meals['post'] ? 1 : 0,
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
     * Mark each BMID as submitted, note what showers & meals were set, and mark any provision items as submitted.
     */

    public function markSubmitted(): void
    {
        $batchInfo = $this->batchInfo ?? '';

        $provisionsByPerson = Provision::retrieveUsableForPersonIds($this->bmids->pluck('person_id'))->groupBy('person_id');

        foreach ($this->bmids as $bmid) {
            $bmid->status = Bmid::SUBMITTED;
            $provisions = $provisionsByPerson->get($bmid->person_id);

            $allocShowers = false;
            $earnedShowers = false;

            $allocMeals = [];
            $earnedMeals = [];

            if ($provisions) {
                foreach ($provisions as $provision) {
                    switch ($provision->type) {
                        case Provision::WET_SPOT:
                            if ($provision->is_allocated) {
                                $allocShowers = true;
                            } else {
                                $earnedShowers = true;
                            }
                            break;

                        case Provision::MEALS:
                            if ($provision->is_allocated) {
                                $meals = &$allocMeals;
                            } else {
                                $meals = &$earnedMeals;
                            }

                            if ($provision->pre_event_meals) {
                                $meals['pre'] = true;
                            }

                            if ($provision->event_week_meals) {
                                $meals['event'] = true;
                            }

                            if ($provision->post_event_meals) {
                                $meals['post'] = true;
                            }
                            break;
                    }
                }
            }

            $showers = [];
            if (!$allocShowers && !$earnedShowers) {
                $showers[] = 'none';
            } else {
                if ($allocShowers) {
                    $showers[] = 'alloc';
                }

                if ($earnedShowers) {
                    $showers[] = 'earned';
                }
            }

            $meals = [];
            if (empty($allocMeals) && empty($earnedMeals)) {
                $meals[] = 'none';
            } else {
                if (!empty($allocMeals)) {
                    $meals[] = Provision::sortMealsMatrix($allocMeals) . ' alloc';
                }

                if (!empty($allocMeals)) {
                    $meals[] = Provision::sortMealsMatrix($earnedMeals) . ' earned';
                }
            }

            if ($provisions) {
                Provision::markSubmittedForBMID($provisions);
            }

            $meals = '[meals ' . implode(', ', $meals) . ']';
            $showers = '[showers ' . implode(', ', $showers) . ']';
            $bmid->appendNotes("Exported $meals $showers");
            $bmid->auditReason = 'exported to print';
            $bmid->batch = $batchInfo;
            $bmid->saveWithoutValidation();
        }
    }
}