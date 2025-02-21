<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class ApprovedCallsignMail extends ProspectiveApplicationMail
{
    public string $viewResource = 'emails.prospective-application.approved-callsign';
    public string $subjectLine = 'your Ranger Radio Handle has been approved!';
}
