<?php

namespace App\Lib;

use App\Models\HandleReservation;
use App\Models\Person;
use App\Models\PersonStatus;

class PotentialClubhouseAccountFromSalesforce
{
    const STATUS_NULL = "null";
    const STATUS_NOTREADY = "notready";
    const STATUS_INVALID = "invalid";
    const STATUS_READY = "ready";
    const STATUS_IMPORTED = "imported";
    const STATUS_SUCCEEDED = "succeeded";
    const STATUS_EXISTING_CALLSIGN = 'existing-callsign';
    const STATUS_EXISTING_CLAIM_CALLSIGN = 'existing-claim-callsign';
    const STATUS_RESERVED_CALLSIGN = 'reserved-callsign';
    const STATUS_EXISTING_BAD_STATUS = 'existing-bad-status';
    const STATUS_EXISTING = 'existing';

    const REQUIRED_RANGER_INFO_FIELDS = [
        'BPGUID__c' => 'Burner Profile GUID',
        'SFUID__c' => 'Salesforce UID',
        'FirstName' => 'First Name',
        'LastName' => 'Last Name',
        'MailingStreet' => 'Street Address',
        'MailingCity' => 'City',
        'MailingState' => 'State',
        'MailingPostalCode' => 'Postal Code',
        'MailingCountry' => 'Country',
        //'npe01__HomeEmail__c', -- This is a backup in case the Ranger Record Contact Email is blank. Totally optional.
        //'Phone',
        //                'Birthdate',
        // March 19, 2019 - BMIT removed the emergency contact from Volunteer Questionnaire.
        // *sigh*
        //        'Emergency_Contact_Name__c',
        //        'Emergency_Contact_Phone__c',
        //        'Emergency_Contact_Relationship__c',
        // The following fields exist but are always blank, at least in the
        // sandbox, so we ignore them
        //            'Email',
        //            'npe01__WorkEmail__c',
        //            'npe01__Preferred_Email__c',
        //            'npe01__PreferredPhone__c',
        //            'npe01__AlternateEmail__c',
        //            'npe01__WorkPhone__c',
        //            'MobilePhone',
        //            'OtherPhone',
    ];

    const PHONE_FIELDS = [
        'Phone',    // Home Phone
        'MobilePhone',
        'OtherPhone',
    ];

    public $status;     /* "null", "invalid", "ready", "imported", "succeeded" */
    public $message;
    public ?Person $existingPerson = null; // Person record which matches callsign, email, sfuid, or bpguid.
    public $applicant_type;
    public $salesforce_ranger_object_id;        /* Internal Salesforce ID */
    public $salesforce_ranger_object_name;      /* "R-201" etc. */
    public $firstname;
    public $lastname;
    public $street1;
    public $city;
    public $state;
    public $zip;
    public $country;
    public $phone;
    public $email;
    public $emergency_contact;
    public $bpguid;
    public $sfuid;
    public $chuid;
    //public $longsleeveshirt_size_style;     /* Fixed to remove multibyte crap */
    //public $teeshirt_size_style;            /* Fixed to remove multibyte crap */
    public $known_pnv_names;        /* PNV = prospective new volunteers */
    public $known_ranger_names;
    public $callsign;
    public $vc_status;
    public $vc_comments;

    public function __construct()
    {
        $this->status = self::STATUS_NOTREADY;     /* placeholder */
        $this->message = "";
    }

    /**
     * Convert a potential Ranger account from Salesforce into the info
     * we need to create a Clubhouse account, sanity checking as we go.
     *
     * "sobj" is a Salesforce object, with all of its horrible naming.
     * We populate the fields in $this (which have more sane names) and
     * clean up the data.
     *
     * At the end, $this->status and $this->message are set accordingly,
     * depending on whether this potential account is good to go.
     *
     * Return TRUE if ok to import, FALSE otherwise.
     * @param $sobj
     * @return bool
     */

    public function convertFromSalesforceObject($sobj): bool
    {
        /*
        * The following just copies the various fields from the SF object
        * into more reasonably named fields, so that higher levels in the
        * Clubhouse don't have to be aware of the cthuluhian horror of the
        * Salesforce object naming scheme.
        */

        $rInfo = $sobj->Ranger_Info__r;
        $this->salesforce_ranger_object_name = self::sanitizeField($sobj, 'Name');
        $this->salesforce_ranger_object_id = self::sanitizeField($sobj, 'Id');
        $this->applicant_type = self::sanitizeField($sobj, 'Ranger_Applicant_Type__c');
        $this->firstname = self::sanitizeField($rInfo, 'FirstName');
        $this->lastname = self::sanitizeField($rInfo, 'LastName');
        $this->street1 = self::sanitizeStreet($rInfo);
        $this->city = self::sanitizeField($rInfo, 'MailingCity');
        $this->state = self::sanitizeField($rInfo, 'MailingState');
        $this->zip = self::sanitizeField($rInfo, 'MailingPostalCode');
        $this->country = self::sanitizeField($rInfo, 'MailingCountry');
        //$this->phone = self::sanitizeField($rInfo, 'Phone');
        $this->email = self::sanitizeField($sobj, 'Contact_Email__c');
        if (empty($this->email)) {
            $this->email = self::sanitizeField($rInfo, 'npe01__HomeEmail__c');
        }
        $ecName = self::sanitizeField($rInfo, 'Emergency_Contact_Name__c');
        $ecRelation = self::sanitizeField($rInfo, 'Emergency_Contact_Relationship__c');
        $ecPhone = self::sanitizeField($rInfo, 'Emergency_Contact_Phone__c');
        $ec = [];
        if (!empty($ecName)) {
            $ec[] = $ecName;
        }

        if (!empty($ecRelation)) {
            $ec[] = "($ecRelation)";
        }

        if (!empty($ecPhone)) {
            $ec[] = "phone $ecPhone";
        }
        $this->emergency_contact = implode(" ", $ec);

        $this->bpguid = self::sanitizeField($rInfo, 'BPGUID__c');
        $this->sfuid = self::sanitizeField($rInfo, 'SFUID__c');
        $this->chuid = self::sanitizeField($sobj, 'CH_UID__c');
        $this->known_pnv_names = self::sanitizeField($sobj, 'Known_Prospective_Volunteer_Names__c');
        $this->known_ranger_names = self::sanitizeField($sobj, 'Known_Rangers_Names__c');
        $this->callsign = self::sanitizeField($sobj, 'VC_Approved_Radio_Call_Sign__c');
        $this->vc_status = self::sanitizeField($sobj, 'VC_Status__c');
        $this->vc_comments = self::sanitizeField($sobj, 'VC_Comments__c');

        // Shirts no longer part of the VolQ.
        //$this->longsleeveshirt_size_style = self::sanitizeLongsleeveshirtSizeStyle(self::sanitizeField($sobj, 'Long_Sleeve_Shirt_Size__c)');
        //$this->teeshirt_size_style = self::sanitizeTeeshirtSizeStyle(self::sanitizeField($sobj, 'Tee_Shirt_Size__c)');

        if ($this->vc_status == "Released to Upload"
            && ($this->applicant_type == "Prospective New Volunteer - Black Rock Ranger"
                || $this->applicant_type == "Prospective New Volunteer - Black Rock Ranger Redux"
            )
        ) {
            $this->status = self::STATUS_READY;
        } else if ($this->vc_status == "Clubhouse Record Created") {
            $this->status = self::STATUS_IMPORTED;
        }

        if ($this->callsign == "") {
            $this->status = self::STATUS_INVALID;
            $this->message = "VC_Approved_Radio_Call_Sign is blank";
            return false;
        }

        $errors = [];
        $blankFields = [];


        if (!isset($rInfo->SFUID__c)) {
            $errors[] = "Possible Tech Cadre import account permission issue, cannot see Salesforce UID from Contact Record";
        }

        foreach (self::REQUIRED_RANGER_INFO_FIELDS as $req => $label) {
            if ($req == 'MailingState'
                && !in_array($rInfo->{'MailingCountry'} ?? '', ['US', 'CA', 'AU'])) {
                continue;
            }

            if (!isset($rInfo->$req) || empty(trim($rInfo->$req))) {
                $blankFields[] = $label;
            }
        }

        foreach (self::PHONE_FIELDS as $field) {
            if (isset($rInfo->{$field})) {
                $phone = trim($rInfo->{$field});
                if (!empty($phone)) {
                    $this->phone = $phone;
                    break;
                }
            }
        }

        if (empty($this->phone)) {
            $blankFields[] = 'Phone (home, mobile, or other)';
        }

        if (!empty($blankFields)) {
            $errors[] = "Required fields are blank: " . implode(', ', $blankFields);
        }

        if (empty($this->email)) {
            $errors[] = "No email address found. Both Ranger record Contact Email field and Contact Record Home Email fields are blank.";
        }

        if (!empty($errors)) {
            $this->status = self::STATUS_INVALID;
            $this->message = implode(' / ', $errors);
            return false;
        }

        return true;
    }

    /**
     * See if this account already exists in some form.
     * This means: (1) callsign is unique, (2) email is unique, (3) bpguid is
     * unique, (4) sfguid is unique.
     * Sets this->status and this->message appropriately.
     * Only do this for accounts that are presumed ready for import.
     */

    public function checkIfAlreadyExists(): void
    {
        if ($this->status != self::STATUS_READY) {
            return;
        }

        $person = Person::findByCallsign($this->callsign);
        if ($person && $person->bpguid != $this->bpguid) {
            // See if the callsign can be reused from an existing account.
            if ($person->vintage) {
                $this->status = self::STATUS_EXISTING_CALLSIGN;
                $this->message = "Account (status {$person->status}) has vintage callsign";
                $this->existingPerson = $person;
                return;
            } else if ($person->status == Person::DECEASED) {
                $now = now();
                $deceased = PersonStatus::findForTime($person->id, $now);
                if (!$deceased || $deceased->new_status != Person::DECEASED) {
                    // Hmm, cannot figure out when the person passed on, either no record exists,
                    // or the very last status update was NOT to deceased
                    $this->status = self::STATUS_EXISTING_CALLSIGN;
                    $this->message = 'Person deceased but cannot determine if still within the grieving period';
                    $this->existingPerson = $person;
                    return;
                } else {
                    $releaseDate = $deceased->created_at->clone()->addYears(Person::GRIEVING_PERIOD_YEARS);
                    if ($releaseDate->gt($now)) {
                        // Status still within the grieving period
                        $this->status = self::STATUS_EXISTING_CALLSIGN;
                        $this->message = 'Person deceased, still in grieving period (' . Person::GRIEVING_PERIOD_YEARS . ' years). Can be released on ' . $releaseDate->toDateTimeString();
                        $this->existingPerson = $person;
                        return;
                    } else {
                        $this->status = self::STATUS_EXISTING_CLAIM_CALLSIGN;
                        $this->existingPerson = $person;
                        // fall thru to additional checks
                    }
                }
            } else if ($person->status != Person::RETIRED && $person->status != Person::RESIGNED) {
                $this->status = self::STATUS_EXISTING_CALLSIGN;
                $this->message = "Callsign already exists, BPGUIDs do not match, and status is not retired or resigned";
                $this->existingPerson = $person;
                return;
            } else {
                $this->status = self::STATUS_EXISTING_CLAIM_CALLSIGN;
                $this->existingPerson = $person;
                // fall thru to additional checks
            }
        }

        $isReservedMessage = $this->callsignIsReserved();
        if ($isReservedMessage && (!$person || $person->bpguid != $this->bpguid)) {
            $this->status = self::STATUS_RESERVED_CALLSIGN;
            $this->message = $isReservedMessage;
            return;
        }

        $person = Person::where('bpguid', $this->bpguid)->first();
        if ($person) {
            $this->checkExisting('BPGUID', $person);
            return;
        }

        $person = Person::findByEmail($this->email);
        if ($person) {
            $this->checkExisting('email address', $person);
            return;
        }

        $person = Person::where('sfuid', $this->sfuid)->first();
        if ($person) {
            $this->checkExisting('SFUID', $person);
        }
    }

    /**
     * Double check to see if the existing Clubhouse account is safe to update.
     *
     * @param $type
     * @param $person
     * @return void
     */

    public function checkExisting($type, $person): void
    {
        $status = $person->status;
        $this->message = "Account with this {$type} already exists";

        if ($status != Person::AUDITOR
            && $status != Person::PAST_PROSPECTIVE
            && $status != Person::NON_RANGER) {
            $this->status = self::STATUS_EXISTING_BAD_STATUS;
            $this->message .= ' and is not an auditor, past prospective, or non-ranger';
        } else {
            $this->status = self::STATUS_EXISTING;
        }
        $this->existingPerson = $person;
    }

    /**
     * See if this callsign is on the reserved List.
     * @return string|bool a string if on the reserved list, false otherwise
     */

    public function callsignIsReserved(): string|bool
    {
        $callsign = $this->callsign;
        $handles = self::getReservedCallsigns();
        $cookedCallsign = Person::normalizeCallsign($callsign);
        foreach ($handles as $handle) {
            if (Person::normalizeCallsign($handle->handle) == $cookedCallsign) {
                $message = "Is a reserved handle/word ({$handle->getTypeLabel()})";
                if ($handle->expires_on) {
                    $message .= ', expires on '.$handle->expires_on->format('Y-m-d');
                }

                return $message;
            }
        }
        return false;
    }

    /**
     * Street addresses in salesforce can contain \r\n,
     * so we get rid of the \rs and convert the \ns to space.
     * @param $s
     * @return string
     */

    public static function sanitizeStreet($s): string
    {
        $s = $s->MailingStreet ?? '';
        $s = str_replace("\r", "", $s);
        $s = str_replace("\n", " ", $s);
        return trim($s);
    }

    public static function sanitizeField($obj, $name): string
    {
        return trim($obj->{$name} ?? '');
    }

    /**
     * Gets an array of reserved callsigns and words which shouldn't be handles.
     * If style is "raw" (default), you get what's in the file with
     * minimum processing.  If it's "cooked", spaces and dashes are
     * eliminated and everything reduced to lower case.
     *
     */

    public static function getReservedCallsigns()
    {
        return HandleReservation::findForQuery(['active' => true]);
    }
}
