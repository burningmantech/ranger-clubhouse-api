<?php

namespace App\Jobs;

use App\Mail\TrainingSignup;

use App\Models\Person;
use App\Models\PersonSlot;
use App\Models\Slot;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrainingSignupEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param Person $person person to email
     * @param Slot $slot enrolled training session.
     * @return void
     */
    public function __construct(public Person $person, public Slot $slot)
    {
    }

    /**
     * Verify the user is still signed up for a training, and send out the email if so.
     *
     * This handles the case were the person may accidentally sign up and then immediately remove themselves.
     * No email should be sent to avoid confusion.
     *
     * @return void
     */

    public function handle(): void
    {
        $person = $this->person;
        $slot = $this->slot;

        if (PersonSlot::haveSlot($person->id, $slot->id)) {
            mail_to_person($person, new TrainingSignup($slot));
        }
    }
}
