<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

use App\Lib\PotentialClubhouseAccountFromSalesforce;
use App\Lib\SalesforceClubhouseInterface;

use App\Models\Person;
use App\Models\PersonRole;
use App\Models\PersonPosition;

use App\Helpers\SqlHelper;
use App\Mail\WelcomeMail;

class SalesforceController extends ApiController
{
    public function config() {
        return response()->json([
            'config' => [
                'SFEnableWritebacks' => setting('SFEnableWritebacks'),
                'SendWelcomeEmail' => setting('SendWelcomeEmail'),
            ]
        ]);
    }

    public function import() {
        $params = request()->validate([
            'create_accounts'     => 'sometimes|boolean|required',
            'showall'             => 'sometimes|boolean',
            'update_sf'           => 'sometimes|boolean',
            'non_test_accounts'   => 'sometimes|boolean',
            'reset_test_accounts' => 'sometimes|boolean',
        ]);

        $createAccounts = $params['create_accounts'] ?? false;
        $resetTestAccounts = $params['reset_test_accounts'] ?? false;
        $updateSf = $params['update_sf'] ?? false;
        $nonTestAccounts = $params['non_test_accounts'] ?? false;

        $showall = $params['showall'] ?? false;

        $queryOptions = "testing";

        if ($resetTestAccounts) {
            $createAccounts = false;
            $updateSf = false;
            $nonTestAccounts = false;
            $showall = false;
        }

        if ($showall) {
            $createAccounts = false;
            $resetTestAccounts = false;
            $updateSf = false;
            $nonTestAccounts = false;
            $queryOptions = 'showall';
        } else if ($createAccounts) {
            $queryOptions = '';
        }

        $sfch = new SalesforceClubhouseInterface();
        if (!$sfch->auth('production')) {
            return response()->json([
                'status'    => 'error',
                'message'   => "Authentication error: {$sfch->sf->errorMessage}"
            ]);
        }

        $r = $sfch->queryAccountsReadyForImport($queryOptions);
        if ($r == false) {
            return response()->json([
                'status' => 'error',
                'message' => 'Query accounts failed '.  $sfch->sf->errorMessage
            ]);
        }

        $accounts = [ ];
        $errors = [];

        foreach ($r->records as $id => $obj) {
            $pca = new PotentialClubhouseAccountFromSalesforce;
            $pca->convertFromSalesforceObject($obj);
            if ($pca->status == "null") {
                continue;
            }

            $account = [
                'applicant_type'                => $pca->applicant_type,
                'salesforce_ranger_object_id'   => $pca->salesforce_ranger_object_id,
                'salesforce_ranger_object_name' => $pca->salesforce_ranger_object_name,
                'first_name'                     => $pca->firstname,
                'last_name'                      => $pca->lastname,
                'street1'                       => $pca->street1,
                'city'                          => $pca->city,
                'state'                         => $pca->state,
                'zip'                           => $pca->zip,
                'country'                       => $pca->country,
                'phone'                         => $pca->phone,
                'email'                         => $pca->email,
                'emergency_contact'             => $pca->emergency_contact,
                'bpguid'                        => $pca->bpguid,
                'sfuid'                         => $pca->sfuid,
                'chuid'                         => $pca->chuid,
                'longsleeveshirt_size_style'    => $pca->longsleeveshirt_size_style,
                'teeshirt_size_style'           => $pca->teeshirt_size_style,
                'known_pnv_names'               => $pca->known_pnv_names,
                'known_ranger_names'            => $pca->known_ranger_names,
                'callsign'                      => $pca->callsign,
                'vc_status'                     => $pca->vc_status,
            ];

            if ($pca->status == "ready") {
                $pca->checkIfAlreadyExists();
            }

            // Only reset accounts if we're not doing anything else.
            // Some of these checks are redundant w/ the above but we're
            // being extra careful here 'cause the gun is loaded
           if ($resetTestAccounts) {
               $pca->status = "reset";
               $sfch->updateSalesforceVCStatus($pca);
           }

            if ($pca->status == "ready" && $createAccounts) {
                if (!self::createPerson($sfch, $pca, $updateSf)) {
                    $account['message'] = $pca->message;
                } else {
                    $account['chid'] = $pca->chid;
                }
            }

            $account['status'] = $pca->status;
            $account['message'] = $pca->message;

            if ($pca->existingPerson) {
                $person = $pca->existingPerson;
                $account['existing_person'] = [
                    'id'       => $person->id,
                    'callsign' => $person->callsign,
                    'status'   => $person->status,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                ];
            }


            $accounts[] = $account;
        }

        return response()->json([
            'status'    => 'success',
            'accounts'  => $accounts,
        ]);
    }

    private static function createPerson($sfch, $pca, $updateSf) {
        $person = new Person;

        $person->callsign          = $pca->callsign;
        $person->callsign_approved = 1;
        $person->first_name        = $pca->firstname;
        $person->last_name         = $pca->lastname;
        $person->street1           = $pca->street1;
        $person->city              = $pca->city;
        $person->state             = $pca->state;
        $person->zip               = $pca->zip;
        $person->country           = $pca->country;
        $person->home_phone        = $pca->phone;
        $person->email             = $pca->email;
        $person->bpguid            = $pca->bpguid;
        $person->sfuid             = $pca->sfuid;
        $person->emergency_contact = $pca->emergency_contact;

        $person->longsleeveshirt_size_style = $pca->longsleeveshirt_size_style;
        $person->teeshirt_size_style        = $pca->teeshirt_size_style;

        $person->status = Person::PROSPECTIVE;
        $person->password = 'abcdef';

        if (!$person->save()) {
            $message = [];
            foreach ($person->getErrors() as $column => $errors) {
                $message[] = "$column: ".implode(' & ', $errors);
            }
            $pca->message = "Creation error: ".implode(', ', $messages);
            $pca->status = "failed";
            return false;
        }

        // Setup the default roles & positions
        PersonRole::resetRoles($person->id, 'salesforce import', Person::ADD_NEW_USER);
        PersonPosition::resetPositions($person->id, 'salesforce import', Person::ADD_NEW_USER);

        // Send a welcome email to the person if not an auditor
        if (setting('SendWelcomeEmail')) {
            Mail::to($person->email)->send(new WelcomeMail($person));
        }

        $pca->chid = $person->id;
        $pca->status = "succeeded";

        if ($updateSf) {
            $sfch->updateSalesforceVCStatus($pca);
            $sfch->updateSalesforceClubhouseImportStatusMessage($pca);
            $sfch->updateSalesforceClubhouseUserID($pca);

            if ($pca->status != "succeeded") {
                return false;
            }
        }

        return true;
    }
}
