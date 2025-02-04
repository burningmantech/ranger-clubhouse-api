<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class WhyRangerQuestionMail extends ProspectiveApplicationMail
{

    public string $subjectLine = 'Ranger Application is on hold and needs attention!';
    public string $viewResource = 'emails.prospective-application.why-ranger-question';
}
