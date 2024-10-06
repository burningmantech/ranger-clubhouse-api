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
    public function __construct(public Position $position, public Slot $slot, public ?Slot $traineeSlot = null)
    {
    }

    /**
     * Fire off an email when a mentor shift becomes empty.
     *
     * @return void
     */

    public function handle(): void
    {
        if (empty($this->position->contact_email)) {
            return;
        }

        $this->slot->refresh();
        if ($this->traineeSlot) {
            $this->traineeSlot->refresh();
        }

        // Don't bother if the slot has signups or if the trainee slot does not have any signups.
        if ($this->slot->signed_up || ($this->traineeSlot && !$this->traineeSlot->signed_up)) {
            return;
        }

        mail_send(new AlertWhenSignUpsEmptyMail($this->position, $this->slot, $this->traineeSlot ? $this->traineeSlot->signed_up : 0));
    }
}
