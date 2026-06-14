<?php

namespace Tests\Feature;

use App\Lib\SalesforceClubhouseInterface;
use App\Lib\SalesforceConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSalesforceConnector;
use Tests\TestCase;

class SalesforceSeamTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The connector resolves from the container, so callers that don't receive an
     * injected connector still get the substituted fake instead of a live,
     * Guzzle-backed connector.
     *
     * @return void
     */

    public function test_connector_resolves_from_container_so_callers_are_substitutable(): void
    {
        $fake = new FakeSalesforceConnector();
        $fake->queryResult = (object) ['totalSize' => 1, 'records' => [(object) ['Id' => 'a01']]];
        $this->app->instance(SalesforceConnector::class, $fake);

        // No connector passed: the default now resolves the fake from the container.
        $iface = new SalesforceClubhouseInterface();

        $this->assertTrue($iface->auth());

        $result = $iface->queryAccountsReadyForImport();
        $this->assertEquals(1, $result->totalSize);
        $this->assertEquals('a01', $result->records[0]->Id);
    }
}
