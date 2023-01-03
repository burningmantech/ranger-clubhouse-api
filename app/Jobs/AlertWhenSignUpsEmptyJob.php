<?php

namespace App\Jobs;

use App\Mail\AlertWhenSignUpsEmptyMail;
use App\Models\Position;
use App\Models\Slot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AlertWhenSignUpsEmptyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public Position $position, public Slot $mentorSlot, public Slot $menteeSlot)
    {
    }

    /**
     * Fire off an email when a mentor shift becomes empty.
     *
     * @return void
     */

    public function handle()
    {
        if (empty($this->position->contact_email)) {
            return;
        }

        $this->menteeSlot->refresh();
        $this->mentorSlot->refresh();

        // Don't bother if there's mentors signed up OR no mentees are signed up
        if ($this->mentorSlot->signed_up || !$this->menteeSlot->signed_up) {
            return;
        }

        mail_to($this->position->contact_email, new AlertWhenSignUpsEmptyMail($this->position, $this->mentorSlot, $this->menteeSlot->signed_up));
    }
}
