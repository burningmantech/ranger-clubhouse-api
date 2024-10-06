<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ProspectiveApplicantMail;

class MoreHandlesMail extends ProspectiveApplicantMail
{

    public string $subjectLine = 'your Ranger Application needs attention!';
    public string $viewResource = 'emails.prospective-application.more-handles';
}
