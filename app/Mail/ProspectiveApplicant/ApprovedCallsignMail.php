<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ProspectiveApplicantMail;

class ApprovedCallsignMail extends ProspectiveApplicantMail
{
    public string $viewResource = 'emails.prospective-application.approved-callsign';
    public string $subjectLine = 'your Ranger Radio Handle has been approved!';
}
