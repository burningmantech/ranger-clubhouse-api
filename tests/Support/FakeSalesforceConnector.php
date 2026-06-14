<?php

namespace Tests\Support;

use App\Lib\SalesforceConnector;

/**
 * Test double for SalesforceConnector. Overrides the three network methods with
 * canned results so callers can be exercised without hitting Salesforce.
 */

class FakeSalesforceConnector extends SalesforceConnector
{
    public bool $authResult = true;

    public mixed $queryResult = null;

    /** @var array<int, array{objname: string, objid: mixed, fields: mixed}> */
    public array $updates = [];

    public function auth(): bool
    {
        return $this->authResult;
    }

    public function soqlQuery(string $q): mixed
    {
        return $this->queryResult;
    }

    public function objUpdate($objname, $objid, $fields): bool
    {
        $this->updates[] = compact('objname', 'objid', 'fields');

        return true;
    }
}
