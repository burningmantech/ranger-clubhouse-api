<?php

namespace App\Lib;

use App\Models\EventDate;
use App\Models\Person;
use App\Models\PersonOnlineTraining;
use App\Models\PersonPhoto;
use App\Models\Schedule;

class Scheduling
{
    // Personal Information has not been reviewed
    const PI_UNREVIEWED = 'pi-unreviewed';
    // BPGUID has not been linked.
    const BPGUID_MISSING = 'bpguid-missing';
    // Online Course has not been completed
    const OT_MISSING = 'ot-missing';
    // Online Training is not available at the moment
    const OT_DISABLED = 'ot-disabled';

    // Callsign is unapproved
    const CALLSIGN_UNAPPROVED = 'callsign-unapproved';
    // Photo is missing or been rejected
    const PHOTO_UNAPPROVED = 'photo-unapproved';
    // Person is a past prospective.
    const PAST_PROSPECTIVE = 'past-prospective';

    /**
     * Determine if the person is allowed to sign up for shifts, and if not, let them know
     * what requirements are missing.
     *
     * @param Person $person
     * @param int $year
     * @return array
     */

    public static function retrieveSignUpPermission(Person $person, int $year): array
    {
        $personId = $person->id;
        $status = $person->status;
        $callsignApproved = $person->callsign_approved;

        if ($status == Person::PAST_PROSPECTIVE) {
            return [
                'signup_for_all_shifts' => false,
                'signup_for_training' => false,
                'requirements' => [self::PAST_PROSPECTIVE]
            ];
        }

        $otEnabled = setting('OnlineTrainingEnabled');

        $isAuditor = ($status == Person::AUDITOR);
        if ($isAuditor && setting('OnlineTrainingOnlyForAuditors')) {
            return [
                'online_training_only' => true,
                'online_training_enabled' => $otEnabled
            ];
        }

        $isPNV = ($status == Person::PROSPECTIVE || $status == Person::ALPHA);
        $isNonRanger = ($status == Person::NON_RANGER);

        if ($isAuditor || setting('AllowSignupsWithoutPhoto')) {
            // Auditors do not have BMIDs.. or sign ups are allowed explicitly without a photo (DANGEROUS).
            $photoStatus = PersonPhoto::NOT_REQUIRED;
        } else {
            $photoStatus = PersonPhoto::retrieveStatus($person);
        }

        if (setting('OnlineTrainingDisabledAllowSignups')) {
            // OT not required - DANGEROUS
            $otCompleted = true;
        } else {
            $otCompleted = PersonOnlineTraining::didCompleteForYear($personId, current_year());
        }

        $canSignUpForTraining = $otCompleted;
        $canSignUpForAllShifts = true;

        $requirements = [];

        if ($isAuditor || $isPNV) {
            if (!$person->hasReviewedPi()) {
                // Auditors & PNVs MUST review their Personal information first before doing anything else.
                // Everyone is allowed to review at their leisure.
                $requirements[] = self::PI_UNREVIEWED;
            }

            if (!$otCompleted) {
                // .. and must pass Online Training before doing anything else
                $requirements[] = $otEnabled ? self::OT_MISSING : self::OT_DISABLED;
            }
        } else if (!$isNonRanger && !$otCompleted) {
            // Online training not completed. Bad Ranger, no biscuit.
            //$requirements[] = $otEnabled ? self::OT_MISSING : self::OT_DISABLED;
            $canSignUpForTraining = false;
        }

        if (!$isAuditor) {
            // PNVs, Rangers, and Non-Rangers must have an approved callsign, and an approved photo
            if (!$callsignApproved) {
                $requirements[] = self::CALLSIGN_UNAPPROVED;
            }

            if ($photoStatus != PersonPhoto::APPROVED && $photoStatus != PersonPhoto::NOT_REQUIRED) {
                $requirements[] = self::PHOTO_UNAPPROVED;
            }

            // Everyone except Auditors and Non Rangers need to have BPGUID on file.
            if (!$isNonRanger && empty($person->bpguid)) {
                $requirements[] = self::BPGUID_MISSING;
            }
        }

        /*
          New for 2019, everyone has to agree to the org's behavioral standards agreement.
          $missingBehaviorAgreement = !$person->behavioral_agreement;

          July 5th, 2019 - agreement language is slightly broken. Agreement is optional.

          if ($missingBehaviorAgreement) {
             $canSignUpForAllShifts = false;
           }
        */

        if (!empty($requirements)) {
            // hard requirements are not met
            $canSignUpForTraining = false;
            $canSignUpForAllShifts = false;
        }

        return [
            // Can the person sign up for all (except training) shifts?
            'all_signups_allowed' => $canSignUpForAllShifts,
            'online_training_enabled' => $otEnabled,
            // Can the person sign up for training?
            'training_signups_allowed' => $canSignUpForTraining,
            'requirements' => $requirements,
            'recommend_burn_weekend_shift' => self::recommendBurnWeekendShift($person),
        ];
    }

    /**
     * Should a burn weekend shift be recommended to the person?
     * @param Person $person
     * @return bool true if the person does not have any weekend shift signups.
     */

    public static function recommendBurnWeekendShift(Person $person): bool
    {
        $status = $person->status;
        if ($status == Person::ALPHA
            || $status == Person::AUDITOR
            || $status == Person::PROSPECTIVE
            || $status == Person::NON_RANGER) {
            // Do not recommend to PNVs, Auditors, and Non Rangers
            return false;
        }

        list ($start, $end) = EventDate::retrieveBurnWeekendPeriod();

        if (now()->gt($end)) {
            // The current time is past the burn weekend.
            return false;
        }

        return !Schedule::hasSignupInPeriod($person->id, $start, $end);
    }

}
