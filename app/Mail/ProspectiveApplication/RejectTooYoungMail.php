<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class RejectTooYoungMail extends ProspectiveApplicationMail
{
    public string $subjectLine = 'we found a problem with your Ranger Application';
    public string $viewResource = 'emails.prospective-application.reject-too-young';
}
