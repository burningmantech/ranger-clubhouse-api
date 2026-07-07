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

    public object|false $queryResult = false;

    /** @var array<int, object|false> Queue of per-call results, consumed in call order before falling back to $queryResult. */
    public array $queryResults = [];

    public bool $updateResult = true;

    /** @var array<int, array{objname: string, objid: mixed, fields: mixed}> */
    public array $updates = [];

    /** @var array<int, string> Every SOQL statement passed to soqlQuery(), in call order. */
    public array $queries = [];

    public function auth(): bool
    {
        return $this->authResult;
    }

    public function soqlQuery(string $q): object|false
    {
        $this->queries[] = $q;

        return $this->queryResults ? array_shift($this->queryResults) : $this->queryResult;
    }

    public function objUpdate(string $objname, string $objid, array $fields): bool
    {
        $this->updates[] = compact('objname', 'objid', 'fields');

        return $this->updateResult;
    }
}
