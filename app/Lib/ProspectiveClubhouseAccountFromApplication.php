<?php

namespace App\Lib;

use App\Exceptions\AccountSaveException;
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
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Previews or commits the creation of Clubhouse accounts (and updates to eligible
 * existing accounts) from approved prospective applications.
 *
 * @phpstan-type PersonSummary array{id: int, callsign: string, status: string}
 */
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
        //  'state' => 'State', -- only required for counties that have states.
        'postal_code' => 'Postal Code',
        'country' => 'Country',
        'phone' => 'Phone',

        'bpguid' => 'BPGUID',
        'sfuid' => 'SFUID',
    ];

    // Statuses of an existing account that may be safely updated back to prospective.
    const array UPDATABLE_EXISTING_STATUSES = [
        Person::AUDITOR,
        Person::PAST_PROSPECTIVE,
        Person::ECHELON,
    ];

    const array COUNTRIES_REQUIRING_STATES = ['US', 'CA', 'AU'];

    const string APPLICATION_APPROVED_MESSAGE = 'application approved';

    const string REASSIGN_CALLSIGN_REASON = 'Reassigned callsign due to incoming prospective application';

    // Intake note source channel for notes copied from the application.
    const string INTAKE_NOTE_SOURCE = 'vc';

    // ErrorLog event tag for account-creation failures.
    const string ERROR_LOG_TAG = 'application-account-fail';

    // Salesforce object name backing a Ranger record.
    const string SALESFORCE_OBJECT = 'Ranger__c';

    /**
     * Placeholder password assigned to brand-new accounts. It is intentionally a
     * non-hashed literal so it can never match the hashed-password check at login;
     * the applicant sets a real password via the temporary login token in the
     * welcome email before they can sign in.
     */
    const string INITIAL_PLACEHOLDER_PASSWORD = 'abcdef';

    public int $application_id;
    public string $first_name;
    public string $last_name;
    public string $email;
    public string $approved_handle;
    public string $salesforce_id;
    public string $salesforce_name;
    public string $bpguid;

    /** @var list<string> */
    public array $errors = [];

    public string $status;
    public ?int $person_id = null;

    /**
     * The account that was created/updated for this application.
     *
     * @var PersonSummary|null
     */
    public ?array $created_person = null;

    /**
     * The existing account which conflicts with this application.
     *
     * @var PersonSummary|null
     */
    public ?array $conflicting_person = null;

    /**
     * The account whose callsign will be reassigned away.
     *
     * @var PersonSummary|null
     */
    public ?array $reassign_callsign = null;

    /**
     * The existing account (auditor, echelon, past prospective) for the applicant to update back to prospective.
     *
     * @var PersonSummary|null
     */
    public ?array $existing_person = null;

    // Live model for the existing account being updated, if any.
    private ?Person $existingPerson = null;
    // Live model for the account whose callsign is being reassigned, if any.
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
     * Preview or commit to creating new accounts, or updating existing ones, from approved applications.
     *
     * @param bool $commit when false, only previews; when true, performs the writes
     * @return list<self>|false the per-application results, or false if Salesforce auth fails on commit
     */
    public static function execute(bool $commit = false): array|bool
    {
        $sf = null;
        if ($commit) {
            $sf = app(SalesforceConnector::class);
            if (!$sf->auth()) {
                return false;
            }
        }

        $reservedHandles = self::loadReservedHandles();

        $results = [];
        foreach (ProspectiveApplication::retrieveApproved() as $application) {
            $prospective = new self($application, $reservedHandles, $commit, $sf);
            $results[] = $prospective;

            if (!$prospective->verifyRequiredFields()) {
                continue;
            }

            $prospective->checkForExisting();

            if ($commit && $prospective->status === self::STATUS_READY) {
                $prospective->createAccount();
            }
        }

        if (!empty($results) && $commit) {
            self::recordActionLog($results, $commit);
        }

        return $results;
    }

    /**
     * Build a map of normalized reserved handle => reservation.
     *
     * @return array<string, HandleReservation>
     */
    private static function loadReservedHandles(): array
    {
        $reservedHandles = [];
        foreach (HandleReservation::findForQuery(['active' => true]) as $handle) {
            $reservedHandles[Person::normalizeCallsign($handle->handle)] = $handle;
        }

        return $reservedHandles;
    }

    /**
     * Record a privacy-conscious summary of the batch run. The full result objects
     * (which carry applicant PII) are never serialized into the audit log.
     *
     * @param list<self> $results
     */
    private static function recordActionLog(array $results, bool $commit): void
    {
        $summary = array_map(
            static fn(self $result): array => [
                'application_id' => $result->application_id,
                'person_id' => $result->person_id,
                'status' => $result->status,
                'errors' => $result->errors,
            ],
            $results,
        );

        ActionLog::record(Auth::user(),
            'prospective-account-create',
            '',
            [
                'results' => $summary,
                'commit' => $commit,
                'sent_welcome_emails' => setting('SendWelcomeEmail'),
                'updated_salesforce' => setting('SFEnableWritebacks'),
            ]);
    }

    /**
     * Verify the application has all required fields for the current year.
     */
    public function verifyRequiredFields(): bool
    {
        $valid = true;

        if ($this->application->year != current_year()) {
            $this->status = self::STATUS_INVALID;
            $this->errors[] = "Application year {$this->application->year} is not the current year";
            $valid = false;
        }

        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field => $description) {
            if (empty($this->application->{$field})) {
                $missing[] = $description;
            }
        }

        if (in_array($this->application->country, self::COUNTRIES_REQUIRING_STATES, true) && empty($this->application->state)) {
            $missing[] = 'State/Province';
        }

        if (!empty($missing)) {
            $this->status = self::STATUS_INVALID;
            $this->errors[] = "Required fields are blank: " . implode(', ', $missing);
            $valid = false;
        }

        return $valid;
    }

    /**
     * See if the application will conflict with an existing account.
     */
    public function checkForExisting(): void
    {
        $callsignHolder = Person::findByCallsign($this->application->approved_handle);

        if ($this->detectCallsignConflict($callsignHolder)) {
            return;
        }

        if ($this->detectReservedHandleConflict($callsignHolder)) {
            return;
        }

        $this->detectDuplicateIdentityConflict();
    }

    /**
     * Detect a conflict between the approved handle and an existing account belonging
     * to a different person. Marks the account for callsign reassignment when the
     * handle can be reused.
     *
     * @param Person|null $person the current holder of the approved handle, if any
     * @return bool true if a hard conflict was recorded and processing should stop
     */
    private function detectCallsignConflict(?Person $person): bool
    {
        if (!$person || $person->bpguid == $this->application->bpguid) {
            return false;
        }

        if ($person->vintage) {
            $this->markConflict(
                self::STATUS_EXISTING_CALLSIGN,
                "Callsign conflict with existing account with vintage status.",
                $person,
            );
            return true;
        }

        if ($person->status == Person::DECEASED) {
            // Stop processing unless the deceased person's callsign may be released.
            return !$this->canReleaseDeceasedCallsign($person);
        }

        if ($person->status != Person::RETIRED && $person->status != Person::RESIGNED) {
            $this->markConflict(
                self::STATUS_EXISTING_CALLSIGN,
                "Callsign conflict with an existing account. This application's and the account's BPGUIDs do not match, and status is neither retired nor resigned",
                $person,
            );
            return true;
        }

        // Retired or resigned: the callsign may be reassigned.
        $this->markReassign($person);
        return false;
    }

    /**
     * Determine whether a deceased person's callsign may be released for reuse.
     * Records a conflict (and returns false) when the grieving period cannot be
     * determined or has not yet elapsed; otherwise marks the account for reassignment.
     *
     * @return bool true if the callsign may be reassigned
     */
    private function canReleaseDeceasedCallsign(Person $person): bool
    {
        $now = now();
        $deceased = PersonStatus::findForTime($person->id, $now);

        if (!$deceased || $deceased->new_status != Person::DECEASED) {
            // Cannot figure out when the person passed on: either no record exists,
            // or the very last status update was NOT to deceased.
            $this->markConflict(
                self::STATUS_EXISTING_CALLSIGN,
                'Person deceased but cannot determine if still within the grieving period',
                $person,
            );
            return false;
        }

        $releaseDate = $deceased->created_at->clone()->addYears(Person::GRIEVING_PERIOD_YEARS);
        if ($releaseDate->gt($now)) {
            $this->markConflict(
                self::STATUS_EXISTING_CALLSIGN,
                'Callsign conflict with deceased person, and still in the grieving period of '
                . Person::GRIEVING_PERIOD_YEARS . ' years. Callsign can be released on '
                . $releaseDate->toDateTimeString(),
                $person,
            );
            return false;
        }

        $this->markReassign($person);
        return true;
    }

    /**
     * Detect a conflict between the approved handle and the reserved-handle list.
     * An applicant who already owns the reserved handle (matching BPGUID) is exempt.
     *
     * @param Person|null $person the current holder of the approved handle, if any
     * @return bool true if a reserved-handle conflict was recorded
     */
    private function detectReservedHandleConflict(?Person $person): bool
    {
        $reservedMessage = $this->isCallsignReserved();
        if ($reservedMessage === false) {
            return false;
        }

        if ($person && $person->bpguid == $this->application->bpguid) {
            return false;
        }

        $this->status = self::STATUS_RESERVED_CALLSIGN;
        $this->errors[] = $reservedMessage;
        return true;
    }

    /**
     * Detect existing accounts matching the application's email or BPGUID. An eligible
     * match is captured for update; an ineligible match, or two distinct eligible
     * accounts, is recorded as a conflict.
     */
    private function detectDuplicateIdentityConflict(): void
    {
        foreach (['email', 'bpguid'] as $unique) {
            $person = Person::where($unique, $this->application->{$unique})->first();
            if (!$person) {
                continue;
            }

            if (!in_array($person->status, self::UPDATABLE_EXISTING_STATUSES, true)) {
                $this->markConflict(
                    self::STATUS_EXISTING_BAD_STATUS,
                    "Account with the same {$unique} already exists, and is not an auditor, past prospective, or echelon",
                    $person,
                );
                return;
            }

            if ($this->existingPerson && $this->existingPerson->id !== $person->id) {
                // The email and BPGUID resolve to two different existing accounts.
                $this->markConflict(
                    self::STATUS_EXISTING_BAD_STATUS,
                    "The application's email and BPGUID match two different existing accounts (#{$this->existingPerson->id} and #{$person->id})",
                    $person,
                );
                return;
            }

            $this->existing_person = $this->buildPerson($person);
            $this->existingPerson = $person;
        }
    }

    /**
     * Record a hard conflict against a given person and halt further checks.
     */
    private function markConflict(string $status, string $error, Person $person): void
    {
        $this->status = $status;
        $this->errors[] = $error;
        $this->conflicting_person = $this->buildPerson($person);
    }

    /**
     * Mark an existing account for callsign reassignment.
     */
    private function markReassign(Person $person): void
    {
        $this->reassign_callsign = $this->buildPerson($person);
        $this->reassignCallsign = $person;
    }

    /**
     * Build up info on an existing person.
     *
     * @return PersonSummary
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
     * See if this callsign is on the reserved list.
     *
     * @return string|bool a string if on the reserved list, false otherwise
     */
    public function isCallsignReserved(): string|bool
    {
        $cookedCallsign = Person::normalizeCallsign($this->application->approved_handle);
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
     * Create a new account (or update an eligible existing account) from the application.
     *
     * All database mutations run inside a single transaction so a failure rolls back
     * any reassigned callsign and leaves no partially provisioned account. Irreversible
     * external side effects (welcome email, Salesforce writebacks) only fire after a
     * successful commit.
     */
    public function createAccount(): void
    {
        if ($this->existingPerson) {
            $person = $this->existingPerson;
            $isNew = false;
        } else {
            $person = new Person;
            $isNew = true;
        }

        $this->populatePersonFromApplication($person, $isNew);

        if (!$this->persistAccount($person, $isNew)) {
            return;
        }

        $this->person_id = $person->id;
        $this->status = self::STATUS_CREATED;
        $this->created_person = $this->buildPerson($person);

        $this->sendWelcomeEmail($person);
    }

    /**
     * Copy application fields onto the person model.
     */
    private function populatePersonFromApplication(Person $person, bool $isNew): void
    {
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
            $person->password = self::INITIAL_PLACEHOLDER_PASSWORD;
        }

        $person->status = Person::PROSPECTIVE;
        $person->auditReason = self::APPLICATION_APPROVED_MESSAGE;
    }

    /**
     * Atomically reassign any conflicting callsign, save the person, mark the
     * application created, and bootstrap status/roles/positions/intake notes. The
     * person is saved after the callsign is freed so a save failure rolls the
     * reassignment back.
     *
     * @return bool true on success; false if the account could not be saved
     */
    private function persistAccount(Person $person, bool $isNew): bool
    {
        try {
            DB::transaction(function () use ($person, $isNew): void {
                if ($this->reassignCallsign) {
                    $old = $this->reassignCallsign;
                    $old->resetCallsign();
                    $old->auditReason = self::REASSIGN_CALLSIGN_REASON;
                    $old->saveWithoutValidation();
                }

                if (!$person->save()) {
                    throw new AccountSaveException($this->describeValidationErrors($person, $isNew));
                }

                $app = $this->application;
                $app->status = ProspectiveApplication::STATUS_CREATED;
                $app->person_id = $person->id;
                $app->saveWithoutValidation();

                if ($isNew) {
                    // Record the initial status for tracking through the Unified Flagging View
                    PersonStatus::record($person->id, '', Person::PROSPECTIVE, self::APPLICATION_APPROVED_MESSAGE, Auth::id());
                    // Setup the default roles & positions
                    PersonRole::resetRoles($person->id, self::APPLICATION_APPROVED_MESSAGE, Person::ADD_NEW_USER);
                    PersonPosition::resetPositions($person->id, self::APPLICATION_APPROVED_MESSAGE, Person::ADD_NEW_USER);
                }

                foreach ($app->notes()->get() as $note) {
                    PersonIntakeNote::record($person->id, current_year(), self::INTAKE_NOTE_SOURCE, $note->note, false, $note->person_id);
                }

                if (!$this->syncSalesforce($person, $isNew)) {
                    throw new AccountSaveException("Salesforce record update failure.");
                }
            });
        } catch (AccountSaveException $e) {
            $this->errors[] = $e->getMessage();
            $this->status = self::STATUS_CREATE_FAILED;
            ErrorLog::record(self::ERROR_LOG_TAG, [
                'person_id' => $person->id,
                'application_id' => $this->application->id,
                'errors' => $person->getErrors(),
            ]);
            return false;
        } catch (QueryException $e) {
            $this->errors[] = "SQL Error: " . $e->getMessage();
            $this->status = self::STATUS_CREATE_FAILED;
            ErrorLog::recordException($e, self::ERROR_LOG_TAG, [
                'person_id' => $person->id,
                'application_id' => $this->application->id,
            ]);
            return false;
        } catch (Throwable $e) {
            $this->errors[] = ($isNew ? 'Creation' : 'Update') . ' error: ' . $e->getMessage();
            $this->status = self::STATUS_CREATE_FAILED;
            ErrorLog::recordException($e, self::ERROR_LOG_TAG, [
                'person_id' => $person->id,
                'application_id' => $this->application->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Render a person's validation errors into a single message.
     */
    private function describeValidationErrors(Person $person, bool $isNew): string
    {
        $messages = [];
        foreach ($person->getErrors() as $column => $errors) {
            $messages[] = "$column: " . implode(' & ', $errors);
        }

        return ($isNew ? 'Creation' : 'Update') . ' error: ' . implode(', ', $messages);
    }

    /**
     * Send the welcome email with a temporary login token, if enabled. The token is
     * how the applicant sets a real password before their first sign-in.
     */
    private function sendWelcomeEmail(Person $person): void
    {
        if (!setting('SendWelcomeEmail')) {
            return;
        }

        $inviteToken = $person->createTemporaryLoginToken(Person::PNV_INVITATION_EXPIRE);
        mail_send(new WelcomeMail($person, $inviteToken));
    }

    /**
     * Write the account result back to the linked Salesforce Ranger record, if enabled.
     */
    private function syncSalesforce(Person $person, bool $isNew): bool
    {
        if (!$this->sf || !setting('SFEnableWritebacks')) {
            return true;
        }

        $action = $isNew ? 'created' : 'updated';

        return $this->sf->objUpdate(self::SALESFORCE_OBJECT, $this->application->salesforce_id,
            [
                'CH_ImportStatusMessage__c' => date("Y-m-d G:i:s") . ": Clubhouse account successfully {$action}",
                'CH_UID__c' => (string)$person->id,
                'VC_Approved_Radio_Call_Sign__c' => $this->application->approved_handle,
                'VC_Event_Year__c' => (string)$this->application->year,
                'VC_Status__c' => $isNew ? 'Clubhouse Record Created' : 'Clubhouse Record Updated'
            ]
        );
    }
}
