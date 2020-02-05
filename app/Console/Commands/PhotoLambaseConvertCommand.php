<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\LambasePhoto;
use App\Models\Person;
use App\Models\PersonPhoto;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use Intervention\Image\ImageManagerStatic as Image;

class PhotoLambaseConvertCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photo:lambase-convert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Converts Lambase photos into Clubhouse photos';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ini_set('memory_limit', '2G');

        $rows = LambasePhoto::where('status', '!=', 'missing')->with('person:id,callsign,status')->get();

        $storage = PersonPhoto::storage();

        $foundErrors = [];

        foreach ($rows as $row) {
            if (!$row->person) {
                $err = "Record #{$row->id} does not have a valid person #{$row->person_id}";
                $this->error($err);
                $foundErrors[] = $err;
                continue;
            }

            $person = $row->person;
            $status = $person->status;

            $file = sprintf("lambase-photos/id-%05d.jpg", $row->person_id);

            if (!file_exists($file)) {
                if ($status == Person::AUDITOR
                    || $status == Person::DECEASED
                    || $status == Person::DISMISSED
                    || $status == Person::RETIRED
                    || $status == Person::UBERBONKED
                    || $status == Person::BONKED
                    || $status == Person::RESIGNED
                    || $status == Person::PAST_PROSPECTIVE) {
                        continue; // Don't worry
                    }
                $err = "Record #{$row->id} for {$person->callsign} status {$status} missing file [{$row->lambase_image}]";
                $this->error($err);
                $foundErrors[] = $err;
                continue;
            }

            $this->info("Converting {$person->callsign}");

            $image = Image::make($file);

            $photo = new PersonPhoto;
            $photo->person_id = $row->person_id;
            $photo->uploaded_at = $row->lambase_date;
            $photo->upload_person_id = $row->person_id;
            $photo->status = $row->status;
            $timestamp = $row->lambase_date->timestamp;
            $photo->image_filename = "photo-{$row->person_id}-{$timestamp}.jpg";
            $photo->orig_filename = "photo-{$row->person_id}-{$timestamp}-orig.jpg";
            $photo->width = $photo->orig_width = $image->width();
            $photo->height = $photo->orig_height = $image->height();
            $photo->saveWithoutValidation();

            DB::table('person')->where('id', $row->person_id)->update([ 'person_photo_id' => $photo->id ]);

            $contents = file_get_contents($file);
            $storage->put(PersonPhoto::STORAGE_DIR . $photo->orig_filename, $contents);
            $storage->put(PersonPhoto::STORAGE_DIR . $photo->image_filename, $contents);

            gc_collect_cycles();
        }

        if (!empty($foundErrors)) {
            $this->error("The following errors were found:");
            foreach ($foundErrors as $err) {
                $this->error($err);
            }
        } else {
            $this->info("CONGRATS! No errors found.");
        }
    }
}
