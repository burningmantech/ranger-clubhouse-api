<?php

namespace Tests\Feature;

use App\Mail\PhotoPendingMail;
use App\Models\Person;
use App\Models\PersonPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClubhousePhotoPendingCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * When PhotoPendingNotifyEmail is not configured, the command must not attempt
     * to send mail (and therefore must not blow up with an RfcComplianceException
     * from an empty To address).
     *
     * @return void
     */

    public function test_handle_does_not_send_mail_when_notify_email_is_not_set(): void
    {
        $this->setting('PhotoPendingNotifyEmail', '');

        $person = Person::factory()->create();
        PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::SUBMITTED,
        ]);

        Mail::fake();

        $this->artisan('clubhouse:photo-pending');

        Mail::assertNothingSent();
    }

    /**
     * When PhotoPendingNotifyEmail is configured and photos are pending, the command
     * sends the notification email.
     *
     * @return void
     */

    public function test_handle_sends_mail_when_notify_email_is_set_and_photos_are_pending(): void
    {
        $this->setting('PhotoPendingNotifyEmail', 'reviewer@example.com');

        $person = Person::factory()->create();
        PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::SUBMITTED,
        ]);

        Mail::fake();

        $this->artisan('clubhouse:photo-pending');

        Mail::assertQueued(PhotoPendingMail::class, 1);
    }
}
