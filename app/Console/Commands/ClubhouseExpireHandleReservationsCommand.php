<?php

namespace App\Console\Commands;

use App\Mail\ExpiredHandleReservationsMail;
use App\Models\HandleReservation;
use Illuminate\Console\Command;

class ClubhouseExpireHandleReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clubhouse:expire-handle-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes any Handle Reservation records that have expired.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $rows = HandleReservation::expireHandles();

        $expired = $rows->map(fn($r) => [
            'id' => $r->id,
            'type' => $r->getTypeLabel(),
            'handle' => $r->handle,
            'reason' => $r->reason,
            'expires_on' => (string)$r->expires_on,
        ])->toArray();

        mail_to(setting('VCEmail'), new ExpiredHandleReservationsMail($expired));
    }
}
