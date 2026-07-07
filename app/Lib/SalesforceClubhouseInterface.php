<?php

namespace App\Lib;

/*
 * This file contains the bulk of the glue code between Salesforce and
 * the Clubhouse.  The functions here know about both Salesforce and
 * Clubhouse, but aren't really dependent on any Clubhouse data structures
 * (e.g., like a Person class).  You can think of this as middleware
 * between Clubhouse and Salesforce.  In particular, it defines two classes:
 *
 *		PotentialClubhouseAccountFromSalesforce (PCA for short)
 *
 *		SalesforceClubhouseInterface			(SCI for short)
 *
 * The SCI class is responsible for knowing how to query Salesforce
 * and get back a list of accounts ready to import.  It relies on the
 * SalesforceConnector class (see salesforce_connector.php) for
 * transport.
 *
 * The basic deal is that when we make a query to Salesforce via their API,
 * that is, via the SCI class, we generate from json an array of php
 * "Ranger" objects.  The names of fields in these objects come from
 * Salesforce's odd API naming conventions, and in some cases have random
 * non-ASCII characters and stuff in them.
 *
 * That's where the PCA class comes in.  We generate one PCA for each
 * Salesforce Ranger object.  PCA cleans up and sanitizes the stuff we
 * get from Salesforce.
 *
 * PCA has a couple of important fields:
 *
 *	status		Import status of the object, one of "invalid",
 *				"ready", "succeeded", etc.  "invalid" means there's something
 *				borked with the object that would prevent a succesful
 *				import; "ready" means good to go.  "succeeded" means
 *				that we tried the import and it worked.
 *
 *	message		Human readable status message that goes along with status.
 *
 * Every record from Salesforce should include a BPGUID (Burner Profile GUID)
 * as well as a SFUID (Salesforce UID).  These are maintained by Salesforce
 * and we treat them as god-given, though we store them in the Clubhouse
 * so we can make sure we're not dealing with a duplicate record.
 */

// ------------- SalesForceClubhouseInterface class -------------------

class SalesforceClubhouseInterface
{
    public SalesforceConnector $sf;

    const SF_FIELDS = [
        'Id',
        'Name',
        'CH_UID__c',
        'Ranger_Applicant_Type__c',
        //   'Long_Sleeve_Shirt_Size__c',
        //   'Tee_Shirt_Size__c',
        'VC_Approved_Radio_Call_Sign__c',
//        'Why_Ranger_Comments__c',
        'VC_Comments__c',
        'VC_Status__c',
        'Known_Rangers__c',
        'Known_Rangers_Names__c',
//        'Know_Prospective_Volunteers__c',       // Remind me to kill DD
        'Known_Prospective_Volunteer_Names__c',
        'Ranger_Info__r.FirstName',
        'Ranger_Info__r.LastName',
        'Ranger_Info__r.MailingStreet',
        'Ranger_Info__r.MailingCity',
        'Ranger_Info__r.MailingState',
        'Ranger_Info__r.MailingCountry',
        'Ranger_Info__r.MailingPostalCode',

        'Contact_Email__c',
        'Ranger_Info__r.npe01__HomeEmail__c',


//		'Ranger_Info__r.Email',
//		'Ranger_Info__r.npe01__WorkEmail__c',
//		'Ranger_Info__r.npe01__Preferred_Email__c',
//		'Ranger_Info__r.npe01__AlternateEmail__c',

        'Ranger_Info__r.Phone',

//		'Ranger_Info__r.npe01__PreferredPhone__c',
//		'Ranger_Info__r.HomePhone',
		'Ranger_Info__r.MobilePhone',
		'Ranger_Info__r.OtherPhone',
//		'Ranger_Info__r.npe01__WorkPhone__c',

//		'Ranger_Info__r.Birthdate',

        'Ranger_Info__r.BPGUID__c',
        'Ranger_Info__r.SFUID__c',
        'Ranger_Info__r.Emergency_Contact_Name__c',
        'Ranger_Info__r.Emergency_Contact_Phone__c',
        'Ranger_Info__r.Emergency_Contact_Relationship__c',
    ];


    /**
     * If we're provided an SFConnector we'll use it, otherwise
     * we'll create one of our own.
     * @param ?SalesforceConnector $sf
     */

    public function __construct(?SalesforceConnector $sf = null)
    {
        $this->sf = $sf ?? app(SalesforceConnector::class);
    }

    /**
     * Authenticate with salesforce
     *
     * @return bool
     */

    public function auth(): bool
    {
        return $this->sf->auth();
    }


    /**
     * Return an array of (Salesforce) objects of accounts ready to import
     * or FALSE on error.
     *
     * By default, only grabs Rangers from Salesforce who are of VC_Status
     * of "Released to Upload" and who are prospective new volunteers.
     * I.e., doesn't deal with updates to existing Rangers, just n00bs.
     * If options is "showall", returns a list of all objects in Salesforce,
     * whether or not they're ready to be imported.  If options is "testing",
     * returns only Salesforce objects with callsigns like "Testing*".
     *
     * The array returned will need to be cleaned up and converted into
     * a PotentialClubhouseAccountFromSalesforce object (see above).
     *
     * @param string $options
     * @return false|mixed
     */

    public function queryAccountsReadyForImport(string $options = ""): mixed
    {
        $q = 'SELECT ' . implode(', ', self::SF_FIELDS) . ' FROM Ranger__c';

        if ($options == "testing") {
            $q .= " WHERE Ranger_Applicant_Type__c = 'Prospective New Volunteer - Black Rock Ranger'";
            $q .= " AND VC_Approved_Radio_Call_Sign__c LIKE 'Testing%'";
        } elseif ($options != "showall") {
            $q .= " WHERE VC_Status__c = 'Released to Upload' AND ";
            $q .= "(Ranger_Applicant_Type__c = 'Prospective New Volunteer - Black Rock Ranger' OR Ranger_Applicant_Type__c = 'Prospective New Volunteer - Black Rock Ranger Redux')";
            $q .= " AND (NOT VC_Approved_Radio_Call_Sign__c LIKE 'Testing%')";
        }

        $r = $this->sf->soqlQuery($q);
        if (!$r || !isset($r->records)) {
            $this->sf->errorMessage = "Salesforce API query failed for $q: {$this->sf->errorMessage}";
            return false;
        }

        return $r;
    }

    /**
     * Set the Clubhouse import status message in Salesforce for this account.
     *
     * @param PotentialClubhouseAccountFromSalesforce $pca
     * @param bool $isNew
     */

    public function updateSalesforceClubhouseImportStatusMessage(PotentialClubhouseAccountFromSalesforce $pca, bool $isNew): void
    {
        $status = $pca->status;
        $message = $pca->message;
        $d = date("Y-m-d G:i:s");
        $m = "$d: Clubhouse import status: $status";
        if (!$isNew) {
            $m .= " existing account updated\n";
        }
        if ($message != "") {
            $m .= ": $message";
        }
        if (!$this->updateSalesforceField(
            $pca->salesforce_ranger_object_id,
            "CH_ImportStatusMessage__c",
            $m
        )) {
            $pca->message .= " [Warning: Salesforce writeback of import status message failed: {$this->sf->errorMessage}]";
        }
    }

    /**
     * Set the Salesforce VCStatus field in Salesforce to
     * either  "Clubhouse Record Created" or "Released to Upload"
     * depending on state of PCA status.
     *
     * @param PotentialClubhouseAccountFromSalesforce $pca
     * @param bool $isNew
     */

    public function updateSalesforceVCStatus(PotentialClubhouseAccountFromSalesforce $pca, bool $isNew): void
    {
        switch ($pca->status) {
            case "succeeded":
                $vcStatus = $isNew ? "Clubhouse Record Created" : "Clubhouse Record Updated";
                break;
            case "reset":
                $vcStatus = "Released to Upload";
                break;
            default:
                return;
        }

        $pca->vc_status = $vcStatus;

        if (!$this->updateSalesforceField(
            $pca->salesforce_ranger_object_id,
            "VC_Status__c",
            $vcStatus
        )) {
            $pca->message .= " [Warning: Salesforce writeback of VC status failed: {$this->sf->errorMessage}]";
        }
    }

    /**
     * Set the Salesforce Clubhouse UID field.
     * (if the creation succeeded, that is).
     * @param PotentialClubhouseAccountFromSalesforce $pca
     */

    public function updateSalesforceClubhouseUserID(PotentialClubhouseAccountFromSalesforce $pca): void
    {
        if ($pca->status != "succeeded") {
            return;
        }
        if (!$this->updateSalesforceField(
            $pca->salesforce_ranger_object_id,
            "CH_UID__c",
            $pca->chuid
        )) {
            $pca->message .= " [Warning: Salesforce writeback of Clubhouse UID failed: {$this->sf->errorMessage}]";
        }
    }

    /**
     * Update a given Salesforce field ("forcefield"?!) to a given value.
     * Returns TRUE on success (or when writebacks are disabled), FALSE on failure.
     *
     * @param string $id
     * @param string $field
     * @param string $value
     * @return bool
     */

    public function updateSalesforceField(string $id, string $field, string $value): bool
    {
        if (setting('SFEnableWritebacks')) {
            return $this->sf->objUpdate("Ranger__c", $id, [$field => $value]);
        }

        return true;
    }
}
