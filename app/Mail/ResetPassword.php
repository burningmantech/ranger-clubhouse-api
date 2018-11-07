<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetPassword extends Mailable
{
    use Queueable, SerializesModels;

    public $password;
    public $adminEmail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($password, $adminEmail)
    {
        $this->password = $password;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->text('emails.reset-password');
    }
}
