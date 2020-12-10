<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Person;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $adminEmail;
    public $greeting;
    public $resetURL;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($person, $token, $adminEmail)
    {
        $this->adminEmail = $adminEmail;

        switch ($person->status) {
            case Person::AUDITOR:
                $this->greeting = "Auditor {$person->first_name}";
                break;
            case Person::PROSPECTIVE:
            case Person::ALPHA:
                $this->greeting = "Prospective Ranger {$person->callsign}";
                break;
            case Person::NON_RANGER:
                $this->greeting = "Ranger Volunteer {$person->callsign}";
                break;
            default:
                $this->greeting = "Ranger {$person->callsign}";
                break;
        }

        if (config('clubhouse.DeploymentEnvironment') == 'Staging') {
            $server = 'ranger-clubhouse-staging';
        } else {
            $server = 'ranger-clubhouse';
        }
        $this->resetURL = "https://{$server}.burningman.org/client/reset-password?token=$token";
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Ranger Clubhouse password reset')->view('emails.reset-password');
    }
}
