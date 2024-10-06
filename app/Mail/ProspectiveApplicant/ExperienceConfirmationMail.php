<?php

namespace App\Mail\ProspectiveApplicant;

use App\Mail\ProspectiveApplicantMail;

class ExperienceConfirmationMail extends ProspectiveApplicantMail
{
    public string $subjectLine = 'we have a question regarding your Ranger application';
    public string $viewResource = 'emails.prospective-applicant.experience-confirmation';
}
