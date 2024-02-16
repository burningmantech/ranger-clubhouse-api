<?php

namespace App\Lib;

use App\Mail\ProspectiveApplicant\ApprovedCallsignMail;
use App\Mail\ProspectiveApplicant\ExperienceConfirmationMail;
use App\Mail\ProspectiveApplicant\MoreHandlesMail;
use App\Mail\ProspectiveApplicant\PiiIssueMail;
use App\Mail\ProspectiveApplicant\RejectRegionalMail;
use App\Mail\ProspectiveApplicant\RejectTooYoungMail;
use App\Mail\ProspectiveApplicant\RejectUnqualifiedMail;
use App\Mail\ProspectiveApplicant\ReturningRangerMail;
use App\Mail\ProspectiveApplicant\RRNCheckMail;
use App\Models\ErrorLog;
use App\Models\ProspectiveApplication;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ProspectiveApplicationStatusMail
{
    const array STATUS_TO_MAIL = [
        ProspectiveApplication::STATUS_APPROVED => ApprovedCallsignMail::class,
        ProspectiveApplication::STATUS_APPROVED_PII_ISSUE => PiiIssueMail::class,
        ProspectiveApplication::STATUS_MORE_HANDLES => MoreHandlesMail::class,
        ProspectiveApplication::STATUS_REJECT_REGIONAL => RejectRegionalMail::class,
        ProspectiveApplication::STATUS_REJECT_TOO_YOUNG => RejectTooYoungMail::class,
        ProspectiveApplication::STATUS_REJECT_UNQUALIFIED => RejectUnqualifiedMail::class,
        ProspectiveApplication::STATUS_HOLD_RRN_CHECK => RRNCheckMail::class,
        ProspectiveApplication::STATUS_HOLD_QUALIFICATION_ISSUE => ExperienceConfirmationMail::class,
        ProspectiveApplication::STATUS_REJECT_RETURNING_RANGER => ReturningRangerMail::class,
    ];

    /**
     * Send an email based on the new status for an application.
     *
     * @param ProspectiveApplication $application
     * @param string $status
     * @param string|null $message
     * @return void
     * @throws TransportExceptionInterface
     */

    public static function execute(ProspectiveApplication $application, string $status, ?string $message): void
    {
        $mailable = self::STATUS_TO_MAIL[$status] ?? null;
        if (!$mailable) {
            return;
        }

        try {
            Mail::send(new $mailable($application, $status, $message));
        } catch (TransportExceptionInterface $e) {
            ErrorLog::recordException($e, 'email-exception', [
                'type' => 'mail-to',
                'message' => $message
            ]);

            throw $e;
        }
    }
}
