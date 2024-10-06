<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ProspectiveApplicantMail;

class ReturningRangerMail extends ProspectiveApplicantMail
{
    public string $subjectLine = 'concerning your Ranger Application.';
    public string $viewResource = 'emails.prospective-application.returning-ranger';
}
