<?php

namespace App\Console\Commands;

use App\Mail\PhotosExpiredMail;
use App\Models\PersonPhoto;
use Illuminate\Console\Command;

class ClubhouseExpirePhotosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:expire-photos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete archived photos older than 6 months';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $photos = PersonPhoto::retrieveExpiredPhotos();

        $expired = [];
        foreach ($photos as $photo) {
            $photo->auditReason = 'automatic archive deletion';
            $photo->delete();
            $expired[] = [
                'person_photo_id' => $photo->id,
                'person_id' => $photo->person_id,
                'callsign' => $photo->person?->callsign,
                'created_at' => (string)$photo->created_at,
            ];
        }

        if (!empty($expired)) {
            mail_to(setting('PhotosExpiredEmail'), new PhotosExpiredMail($expired));
        }
    }
}
