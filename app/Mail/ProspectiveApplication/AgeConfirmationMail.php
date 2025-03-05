<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class AgeConfirmationMail extends ProspectiveApplicationMail
{
    public string $subjectLine = 'we have a question regarding your Ranger application';
    public string $viewResource = 'emails.prospective-application.age-confirmation';
}
