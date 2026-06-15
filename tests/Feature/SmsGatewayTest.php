<?php

namespace Tests\Feature;

use App\Lib\SmsGateway;
use App\Lib\TwilioSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsGateway;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /**
     * Production resolves the Twilio adapter.
     *
     * @return void
     */

    public function test_default_binding_resolves_to_twilio_gateway(): void
    {
        $this->assertInstanceOf(TwilioSmsGateway::class, app(SmsGateway::class));
    }

    /**
     * Updating a number routes the verification send through the gateway seam.
     *
     * @return void
     */

    public function test_update_numbers_sends_verification_through_gateway(): void
    {
        $fake = new FakeSmsGateway();
        $this->app->instance(SmsGateway::class, $fake);

        $response = $this->json('POST', 'sms', [
            'person_id' => $this->user->id,
            'on_playa' => '+15105551234',
            'off_playa' => '+15105551234',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('numbers.on_playa.code_status', 'sent');

        $this->assertCount(1, $fake->sent);
        $this->assertStringContainsString('verification code', $fake->sent[0]['message']);
    }

    /**
     * A gateway failure is caught and surfaced as a failed send — a path that was
     * impossible to exercise before the seam existed.
     *
     * @return void
     */

    public function test_update_numbers_reports_failure_when_gateway_throws(): void
    {
        $fake = new FakeSmsGateway();
        $fake->throwOnBroadcast = true;
        $this->app->instance(SmsGateway::class, $fake);

        $response = $this->json('POST', 'sms', [
            'person_id' => $this->user->id,
            'on_playa' => '+15105551234',
            'off_playa' => '+15105551234',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('numbers.on_playa.code_status', 'sent-fail');
        $this->assertCount(0, $fake->sent);
    }
}
