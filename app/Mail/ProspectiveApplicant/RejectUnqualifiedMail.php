<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ProspectiveApplicantMail;

class RejectUnqualifiedMail extends ProspectiveApplicantMail
{
    public string $subjectLine = 'we found a problem with your Ranger Application';
    public string $viewResource = 'emails.prospective-application.reject-unqualified';
}
