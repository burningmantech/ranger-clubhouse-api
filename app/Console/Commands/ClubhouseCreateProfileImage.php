<?php

namespace App\Console\Commands;

use App\Models\PersonPhoto;
use Illuminate\Console\Command;
use Intervention\Image\ImageManagerStatic as Image;

class ClubhouseCreateProfileImage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:create-profile-image';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create profile image versions for missing photos';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $photos = PersonPhoto::where('profile_filename', '=', '')
            ->orWhereNull('profile_filename')
            ->get();

        foreach ($photos as $photo) {
            $this->info("Processing person {$photo->person_id} -> {$photo->image_filename}");
            $this->process($photo);
        }
        return 0;
    }

    public function process($photo)
    {
        $image = Image::make($photo->readImage());
        // correct image orientation
        $image->orientate();
        $image->resize(PersonPhoto::PROFILE_WIDTH, PersonPhoto::PROFILE_HEIGHT, function ($constrain) {
            $constrain->aspectRatio();
            $constrain->upsize();
        });

        $contents = $image->stream('jpg', 75)->getContents();
        $width = $image->width();
        $height = $image->height();
        $image->destroy();  // free up memory
        $image = null; // and kill the object
        gc_collect_cycles();     // Images can be huge, garbage collect.

        $photo->storeImage($contents, now()->timestamp, PersonPhoto::SIZE_PROFILE);
        $photo->profile_height = $height;
        $photo->profile_width = $width;
        $photo->auditReason = 'profile image version created';
        $photo->saveWithoutValidation();
    }
}
