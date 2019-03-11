<?php

namespace App\Http\Controllers;

use App\Lib\PotentialClubhouseAccountFromSalesforce;
use App\Lib\SalesforceClubhouseInterface;

use Illuminate\Http\Request;

class SalesforceController extends ApiController
{
    public function config() {
        return response()->json([
            'config' => [
                'SFEnableWritebacks' => setting('SFEnableWritebacks')
            ]
        ]);
    }

    public function import() {
        $params = request()->validate([
            'commit'     => 'sometimes|boolean|required',
            'environment' => 'sometimes|string'
        ]);

        $commit = $params['commit'] ?? false;
        $environment = $params['environment'] ?? "production";

        $sfch = new SalesforceClubhouseInterface();
        if (!$sfch->auth($environment)) {
            return response()->json([
                'status'    => 'error',
                'message'   => "Authentication error: {$sfch->sf->errorMessage}"
            ]);
        }


        $r = $sfch->queryAccountsReadyForImport('');
        if ($r == false) {
            return response()->json([ 'status' => 'error', 'message' => 'Query accounts failed '.  $sfch->sf->errorMessage]);
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

            if ($pca->status != "ready") {
                // XXX Should update Salesforce that we declined to process
                $account['message'] = $pca->message;
            }

            $account['status'] = $pca->status;
            if ($pca->existingPerson) {
                $person = $pca->existingPerson;
                $account['existing_person'] = [
                    'id'       => $person->id,
                    'callsign' => $person->callsign,
                    'status'   => $person->status
                ];
            }

            // Only reset accounts if we're not doing anything else.
            // Some of these checks are redundant w/ the above but we're
            // being extra careful here 'cause the gun is loaded
/*            if ($resetTestAccounts && !$showAll && !$createAccounts
                    && !$forReals) {
                $this->resetSalesforceVCStatus($sfch, $pca);
            }
            if ($pca->status == "ready" && $commit) {
                $this->createAccount($sfch, $pca);
            }*/

            $accounts[] = $account;
        }

        return response()->json([
            'status'    => 'success',
            'accounts'  => $accounts,
        ]);
    }

}
