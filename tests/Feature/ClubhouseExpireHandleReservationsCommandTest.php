<?php

namespace Tests\Feature;

use App\Mail\ExpiredHandleReservationsMail;
use App\Models\HandleReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClubhouseExpireHandleReservationsCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The mailed 'expires_on' value must be a plain date (Y-m-d), not a
     * datetime string with a spurious midnight timestamp appended.
     *
     * @return void
     */

    public function test_handle_mails_expires_on_as_plain_date(): void
    {
        Mail::fake();

        HandleReservation::factory()->create([
            'handle' => 'Hubcap',
            'reservation_type' => HandleReservation::TYPE_OBSCENE,
            'reason' => 'testing',
            'expires_on' => '2025-12-31',
        ]);

        $this->artisan('clubhouse:expire-handle-reservations')
            ->assertExitCode(0);

        Mail::assertQueued(ExpiredHandleReservationsMail::class, function ($mail) {
            return $mail->handles[0]['expires_on'] === '2025-12-31';
        });
    }
}
