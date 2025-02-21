<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class MoreHandlesMail extends ProspectiveApplicationMail
{

    public string $subjectLine = 'your Ranger Application needs attention!';
    public string $viewResource = 'emails.prospective-application.more-handles';
}
