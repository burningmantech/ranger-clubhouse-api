<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class ReturningRangerMail extends ProspectiveApplicationMail
{
    public string $subjectLine = 'concerning your Ranger Application.';
    public string $viewResource = 'emails.prospective-application.returning-ranger';
}
