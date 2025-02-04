<?php

namespace App\Mail\ProspectiveApplication;

use App\Mail\ProspectiveApplicationMail;

class ExperienceConfirmationMail extends ProspectiveApplicationMail
{
    public string $subjectLine = 'we have a question regarding your Ranger application';
    public string $viewResource = 'emails.prospective-application.experience-confirmation';
}
