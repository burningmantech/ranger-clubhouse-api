<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPerson;
use App\Models\Person;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AssetControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /**
     * Create an asset for the current year.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createAsset(array $attributes = []): Asset
    {
        return Asset::create(array_merge([
            'barcode' => 'C-' . $this->faker->unique()->numerify('####'),
            'type' => Asset::TYPE_RADIO,
            'year' => current_year(),
        ], $attributes));
    }

    /*
     * A user holding only EVENT_MANAGEMENT (not admin) can show an asset.
     *
     * Regression: show() previously authorized the non-existent 'show' ability,
     * which denied every user because the policy's before() is never consulted
     * for an uncallable ability. It must map to the 'view' policy method.
     */

    public function testShowAllowsEventManagementUser(): void
    {
        $this->signInWithRole(Role::EVENT_MANAGEMENT);
        $asset = $this->createAsset();

        $response = $this->json('GET', "asset/{$asset->id}");

        $response->assertStatus(200);
        $response->assertJson(['asset' => ['id' => $asset->id, 'barcode' => $asset->barcode]]);
    }

    /*
     * An admin can show an asset via the policy before() short-circuit.
     */

    public function testShowAllowsAdmin(): void
    {
        $this->signInAsAdmin();
        $asset = $this->createAsset();

        $response = $this->json('GET', "asset/{$asset->id}");

        $response->assertStatus(200);
        $response->assertJson(['asset' => ['id' => $asset->id]]);
    }

    /*
     * A user without EVENT_MANAGEMENT (or admin) is denied.
     */

    public function testShowDeniedWithoutRole(): void
    {
        $this->signInUser();
        $asset = $this->createAsset();

        $response = $this->json('GET', "asset/{$asset->id}");

        $response->assertStatus(403);
    }

    /*
     * The asset history must not leak the check-out / check-in person's PII.
     *
     * Regression: retrieveHistory() loaded check_out_person / check_in_person
     * with no column projection, serializing the full Person row (email, phone,
     * address, etc.). It must restrict those relations to id + callsign.
     */

    public function testHistoryDoesNotExposeCheckInOutPersonPii(): void
    {
        $this->signInAsAdmin();
        $asset = $this->createAsset();

        $checkoutClerk = Person::factory()->create([
            'callsign' => 'CheckoutClerk',
            'email' => 'checkout-clerk-secret@example.com',
        ]);
        $holder = Person::factory()->create([
            'callsign' => 'AssetHolder',
            'email' => 'holder-secret@example.com',
        ]);

        AssetPerson::create([
            'asset_id' => $asset->id,
            'person_id' => $holder->id,
            'check_out_person_id' => $checkoutClerk->id,
            'checked_out' => now(),
            'checked_in' => now()->addHour(),
            'check_in_person_id' => $checkoutClerk->id,
        ]);

        $response = $this->json('GET', "asset/{$asset->id}/history");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['asset_history']);

        // The callsign is allowed; the PII columns must not appear anywhere.
        $response->assertSee('CheckoutClerk');
        $response->assertDontSee('checkout-clerk-secret@example.com');
        $response->assertDontSee('holder-secret@example.com');
    }

    /*
     * Checking in an outstanding asset succeeds and reports the check-in time.
     */

    public function testCheckinSucceeds(): void
    {
        $this->signInWithRole(Role::EVENT_MANAGEMENT);
        $asset = $this->createAsset();
        $holder = Person::factory()->create();

        $assetPerson = AssetPerson::create([
            'asset_id' => $asset->id,
            'person_id' => $holder->id,
            'checked_out' => now(),
        ]);

        $response = $this->json('POST', "asset/{$asset->id}/checkin");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
        $this->assertNotNull($assetPerson->fresh()->checked_in);
        $this->assertEquals($this->user->id, $assetPerson->fresh()->check_in_person_id);
    }

    /*
     * Checking in an asset that is not checked out raises a 422.
     */

    public function testCheckinFailsWhenNotCheckedOut(): void
    {
        $this->signInWithRole(Role::EVENT_MANAGEMENT);
        $asset = $this->createAsset();

        $response = $this->json('POST', "asset/{$asset->id}/checkin");

        $response->assertStatus(422);
    }

    /*
     * Contract behind the checkin restError() fix: when an AssetPerson save fails
     * validation it carries the failure messages, whereas an Asset that was never
     * saved carries none. checkin() must therefore surface errors from the
     * AssetPerson that failed, not the Asset.
     */

    public function testRestErrorMustUseTheFailedAssetPersonModel(): void
    {
        $asset = $this->createAsset();
        $this->assertNull($asset->getErrors());

        $failingAssetPerson = new AssetPerson(['asset_id' => $asset->id]);

        $this->assertFalse($failingAssetPerson->save());
        $this->assertNotNull($failingAssetPerson->getErrors());
        $this->assertArrayHasKey('person_id', $failingAssetPerson->getErrors());
    }
}
