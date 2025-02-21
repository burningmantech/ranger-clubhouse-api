<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class PiiIssueMail extends ProspectiveApplicationMail
{
    public string $subjectLine = 'your Ranger application is missing some info';
    public string $viewResource = 'emails.prospective-application.pii-issue';
}
