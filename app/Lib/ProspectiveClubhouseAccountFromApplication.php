<?php

namespace App\Lib;

use App\Mail\WelcomeMail;
use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\HandleReservation;
use App\Models\Person;
use App\Models\PersonIntakeNote;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonStatus;
use App\Models\ProspectiveApplication;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;

class ProspectiveClubhouseAccountFromApplication
{
    // Application has missing fields.
    const string STATUS_INVALID = "invalid";

    // An account can be created from application
    const string STATUS_READY = "ready";
    // Account can be created from application, and the callsign be reassigned.
    const string STATUS_READY_REASSIGN_CALLSIGN = 'ready-reassign-callsign';

    // Account was created successfully
    const string STATUS_CREATED = "created";
    // Could not create the account
    const string STATUS_CREATE_FAILED = 'create-failed';

    // Conflict with an existing account with the desired callsign
    const string STATUS_EXISTING_CALLSIGN = 'existing-callsign';
    // Conflict with a reserved callsign or operational word/phrase
    const string STATUS_RESERVED_CALLSIGN = 'reserved-callsign';
    // An account already exists with the same BPGUID or email as the application,
    // however, said account is not auditor, non-ranger, or past prospective.
    const string STATUS_EXISTING_BAD_STATUS = 'existing-bad-status';

    const array REQUIRED_FIELDS = [
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'email' => 'Email',
        'approved_handle' => 'Approved Handle',

        'street' => 'Street',
        'city' => 'City',
        'state' => 'State',
        'postal_code' => 'Postal Code',
        'country' => 'Country',
        'phone' => 'Phone',

        'bpguid' => 'BPGUID',
        'sfuid' => 'SFUID',
    ];

    const string APPLICATION_APPROVED_MESSAGE = 'application approved';

    public int $application_id;
    public string $first_name;
    public string $last_name;
    public string $email;
    public string $approved_handle;
    public string $salesforce_id;
    public string $salesforce_name;
    public string $bpguid;
    public array $errors = [];
    public string $status;
    public ?int $person_id = null;

    // The account that was created for this application
    public ?array $created_person = null;
    // The existing account which conflicts with this application
    public ?array $conflicting_person = null;
    // The account whose callsign will be reassigned from
    public ?array $reassign_callsign = null;
    // The existing account (auditor, non ranger, past prospective) for the applicant to update back to prospective.
    public ?array $existing_person = null;

    private ?Person $existingPerson = null;
    private ?Person $reassignCallsign = null;

    public function __construct(private readonly ProspectiveApplication $application,
                                private readonly array                  $reservedHandles,
                                private readonly bool                   $commit,
                                private readonly ?SalesforceConnector   $sf = null
    )
    {
        $this->status = self::STATUS_READY;

        $app = $this->application;
        $this->first_name = $app->first_name;
        $this->last_name = $app->last_name;
        $this->bpguid = $app->bpguid;
        $this->email = $app->email;
        $this->application_id = $app->id;
        $this->approved_handle = $app->approved_handle;
        $this->salesforce_id = $app->salesforce_id;
        $this->salesforce_name = $app->salesforce_name;
    }

    /**
     * Preview or commit to creating new account, or updating existing ones, from approved applications.
     *
     * @param bool $commit
     * @return array|bool
     */
    public static function execute(bool $commit = false): array|bool
    {
        if ($commit) {
            $sf = new SalesforceConnector();
            if (!$sf->auth()) {
                return false;
            }
        } else {
            $sf = null;
        }

        $applications = ProspectiveApplication::retrieveApproved();

        $handles = HandleReservation::findForQuery(['active' => true]);

        $reservedHandles = [];
        foreach ($handles as $handle) {
            $reservedHandles[Person::normalizeCallsign($handle->handle)] = $handle;
        }

        $results = [];
        foreach ($applications as $application) {
            $prospective = new ProspectiveClubhouseAccountFromApplication($application, $reservedHandles, $commit, $sf);
            $results[] = $prospective;
            if (!$prospective->verifyRequiredFields()) {
                continue;
            }
            $prospective->checkForExisting();

            if (!$commit) {
                continue;
            }

            if ($prospective->status == self::STATUS_READY) {
                $prospective->createAccount();
            }
        }

        if (!empty($results) && $commit) {
            ActionLog::record(Auth::user(),
                'prospective-account-create',
                '',
                [
                    'results' => $results,
                    'commit' => $commit,
                    'sent_welcome_emails' => setting('SendWelcomeEmail'),
                    'updated_salesforce' => setting('SFEnableWritebacks'),
                ]);
        }

        return $results;
    }


    public function verifyRequiredFields(): bool
    {
        if ($this->application->year != current_year()) {
            $this->status = self::STATUS_INVALID;
            $this->errors[] = "Application year {$this->application->year} is not the current year";
            // fall thru
        }

        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field => $description) {
            if (empty($this->application->{$field})) {
                $missing[] = $description;
            }
        }

        if (empty($missing)) {
            return true;
        }

        $this->status = self::STATUS_INVALID;
        $this->errors[] = "Required fields are blank: " . implode(', ', $missing);
        return false;
    }

    /**
     * See if the application will conflict with an existing account.
     *
     * @return void
     */

    public function checkForExisting(): void
    {
        $application = $this->application;

        $person = Person::findByCallsign($application->approved_handle);
        if ($person && $person->bpguid != $application->bpguid) {
            // See if the callsign can be reused from an existing account.
            if ($person->vintage) {
                $this->status = self::STATUS_EXISTING_CALLSIGN;
                $this->errors[] = "Callsign conflict with existing account with vintage status.";
                $this->conflicting_person = $this->buildPerson($person);
                return;
            } else if ($person->status == Person::DECEASED) {
                $now = now();
                $deceased = PersonStatus::findForTime($person->id, $now);
                if (!$deceased || $deceased->new_status != Person::DECEASED) {
                    // Hmm, cannot figure out when the person passed on, either no record exists,
                    // or the very last status update was NOT to deceased
                    $this->status = self::STATUS_EXISTING_CALLSIGN;
                    $this->errors[] = 'Person deceased but cannot determine if still within the grieving period';
                    $this->conflicting_person = $this->buildPerson($person);
                    return;
                } else {
                    $releaseDate = $deceased->created_at->clone()->addYears(Person::GRIEVING_PERIOD_YEARS);
                    if ($releaseDate->gt($now)) {
                        // Status still within the grieving period
                        $this->status = self::STATUS_EXISTING_CALLSIGN;
                        $this->errors[] = 'Callsign conflict with deceased person, and still in the grieving period of ' . Person::GRIEVING_PERIOD_YEARS . ' years. Callsign can be released on ' . $releaseDate->toDateTimeString();
                        $this->conflicting_person = $this->buildPerson($person);
                        return;
                    } else {
                        $this->reassign_callsign = $this->buildPerson($person);
                        $this->reassignCallsign = $person;
                        // fall thru to additional checks
                    }
                }
            } else if ($person->status != Person::RETIRED && $person->status != Person::RESIGNED) {
                $this->status = self::STATUS_EXISTING_CALLSIGN;
                $this->errors[] = "Callsign conflict with an existing account. This application's and the account's BPGUIDs do not match, and status is neither retired nor resigned";
                $this->conflicting_person = $this->buildPerson($person);
                return;
            } else {
                $this->reassign_callsign = $this->buildPerson($person);
                $this->reassignCallsign = $person;
                // fall thru to additional checks
            }
        }

        $isReservedMessage = $this->isCallsignReserved();
        if ($isReservedMessage && (!$person || $person->bpguid != $application->bpguid)) {
            $this->status = self::STATUS_RESERVED_CALLSIGN;
            $this->errors[] = $isReservedMessage;
            return;
        }

        foreach (['email', 'bpguid'] as $unique) {
            $person = Person::where($unique, $application->{$unique})->first();
            if (!$person) {
                continue;
            }

            $status = $person->status;
            if ($status == Person::AUDITOR
                || $status == Person::PAST_PROSPECTIVE
                || $status == Person::NON_RANGER) {
                // Totally fine if the account is updated to prospective status.
                $this->existing_person = $this->buildPerson($person);
                $this->existingPerson = $person;
            } else {
                $this->status = self::STATUS_EXISTING_BAD_STATUS;
                $this->errors[] = "Account with the same {$unique} already exists, and is not an auditor, past prospective, or non-ranger";
                $this->conflicting_person = $this->buildPerson($person);
                return;
            }
        }
    }

    /**
     * Build up info on a existing person
     *
     * @param Person $person
     * @return array
     */

    public function buildPerson(Person $person): array
    {
        return [
            'id' => $person->id,
            'callsign' => $person->callsign,
            'status' => $person->status
        ];
    }

    /**
     * See if this callsign is on the reserved List.
     * 
     * @return string|bool a string if on the reserved list, false otherwise
     */

    public function isCallsignReserved(): string|bool
    {
        $callsign = $this->application->approved_handle;
        $cookedCallsign = Person::normalizeCallsign($callsign);
        $reserved = $this->reservedHandles[$cookedCallsign] ?? null;
        if (!$reserved) {
            return false;
        }

        $message = "Is a reserved handle/word ({$reserved->getTypeLabel()})";
        if ($reserved->expires_on) {
            $message .= ', expires on ' . $reserved->expires_on->format('Y-m-d');
        }

        return $message;
    }

    /**
     * Create an account from an application
     */

    public function createAccount(): void
    {
        if ($this->existingPerson) {
            $person = $this->existingPerson;
            $isNew = false;
        } else {
            $person = new Person;
            $isNew = true;
            if ($this->reassignCallsign) {
                $old = $this->reassignCallsign;
                $old->resetCallsign();
                $old->auditReason = 'Reassigned callsign due to incoming prospective application';
                $old->saveWithoutValidation();
            }
        }

        $app = $this->application;

        $person->callsign = $app->approved_handle;
        $person->callsign_approved = true;
        $person->first_name = $app->first_name;
        $person->last_name = $app->last_name;
        $person->street1 = $app->street;
        $person->city = $app->city;
        $person->state = $app->state;
        $person->zip = $app->postal_code;
        $person->country = $app->country;
        $person->home_phone = $app->phone;
        $person->email = $app->email;
        $person->bpguid = $app->bpguid;
        $person->sfuid = $app->sfuid;
        $person->emergency_contact = $app->emergency_contact;

        $person->known_rangers = $app->known_ranger_names;
        $person->known_pnvs = $app->known_pnv_names;

        if ($isNew) {
            $person->password = 'abcdef';
        } else {
            $oldStatus = $person->status;
        }

        $person->status = Person::PROSPECTIVE;
        $person->auditReason = self::APPLICATION_APPROVED_MESSAGE;

        try {
            if (!$person->save()) {
                $message = [];
                foreach ($person->getErrors() as $column => $errors) {
                    $message[] = "$column: " . implode(' & ', $errors);
                }
                $this->errors[] = ($isNew ? 'Creation' : 'Update') . ' error: ' . implode(', ', $message);
                $this->status = self::STATUS_CREATE_FAILED;
                ErrorLog::record('application-account-fail', [
                    'person' => $person,
                    'errors' => $person->getErrors(),
                    'application' => $this->application,
                ]);
                return;
            }
        } catch (QueryException $e) {
            $this->errors[] = "SQL Error: " . $e->getMessage();
            $this->status = self::STATUS_CREATE_FAILED;
            ErrorLog::recordException($e, 'application-account-fail', [
                'person' => $person,
                'application' => $this->application,
            ]);
            return;
        }

        $this->status = self::STATUS_CREATED;
        $app->status = ProspectiveApplication::STATUS_CREATED;
        $app->person_id = $person->id;
        $app->saveWithoutValidation();
        $this->created_person = [
            'id' => $person->id,
            'callsign' => $person->callsign,
        ];

        if ($isNew) {
            // Record the initial status for tracking through the Unified Flagging View
            PersonStatus::record($person->id, '', Person::PROSPECTIVE, self::APPLICATION_APPROVED_MESSAGE, Auth::id());
            // Setup the default roles & positions
            PersonRole::resetRoles($person->id, self::APPLICATION_APPROVED_MESSAGE, Person::ADD_NEW_USER);
            PersonPosition::resetPositions($person->id, self::APPLICATION_APPROVED_MESSAGE, Person::ADD_NEW_USER);
        }

        $notes = $app->notes()->get();

        foreach ($notes as $note) {
            PersonIntakeNote::record($person->id, current_year(), 'vc', $note->note, false, $note->person_id);
        }

        // Send a welcome email to the person if not an auditor
        if (setting('SendWelcomeEmail')) {
            $inviteToken = $person->createTemporaryLoginToken(Person::PNV_INVITATION_EXPIRE);
            mail_to_person($person, new WelcomeMail($person, $inviteToken), true);
        }

        if ($this->commit && setting('SFEnableWritebacks')) {
            $this->updateSalesforceField(
                'VC_Status__c',
                $isNew ? 'Clubhouse Record Created' : 'Clubhouse Record Updated'
            );
            $this->updateSalesforceField(
                'VC_Approved_Radio_Call_Sign__c',
                $this->application->approved_handle,
            );

            $this->updateSalesforceField('CH_UID__c', $person->id);
            $this->updateSalesforceField('VC_Event_Year__c', $app->year);
            $msg = date("Y-m-d G:i:s")
                . ': Clubhouse '
                . ($isNew ? 'existing account successfully update' : ' account successfully created');
            $this->updateSalesforceField('CH_ImportStatusMessage__c', $msg);
        }
    }

    /**
     * Update a given Salesforce field ("forcefield"?!) to a given value.
     *
     * @param string $field
     * @param string $value
     */

    public function updateSalesforceField(string $field, string $value): void
    {
        $this->sf->objUpdate("Ranger__c", $this->application->salesforce_id, [$field => $value]);
    }
}