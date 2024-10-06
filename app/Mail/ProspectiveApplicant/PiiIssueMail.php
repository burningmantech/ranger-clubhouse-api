<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ProspectiveApplicantMail;

class PiiIssueMail extends ProspectiveApplicantMail
{
    public string $subjectLine = 'your Ranger application is missing some info';
    public string $viewResource = 'emails.prospective-applicant.pii-issue';
}
