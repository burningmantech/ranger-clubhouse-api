<?php

namespace Tests\Feature;

use App\Lib\RBS;
use App\Lib\SmsGateway;
use App\Models\Person;
use App\Models\PersonMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsGateway;
use Tests\TestCase;

class RBSTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->setting('BroadcastClubhouseNotify', true);
        $this->setting('BroadcastSMSSandbox', false);
        $this->setting('BroadcastMailSandbox', true);
    }

    /**
     * The SMS subject is truncated to the actual remaining character budget,
     * not to the length of the message prefix.
     *
     * @return void
     */

    public function test_clubhouse_message_notify_truncates_subject_to_remaining_sms_budget(): void
    {
        $fake = new FakeSmsGateway();
        $this->app->instance(SmsGateway::class, $fake);

        $person = Person::factory()->create([
            'sms_on_playa' => '+15105551234',
            'sms_on_playa_verified' => true,
            'sms_on_playa_stopped' => false,
        ]);

        $from = 'Someone';
        $subject = str_repeat('A very long subject line indeed ', 5);

        RBS::clubhouseMessageNotify($person, $person->id, $from, $subject, 'body', PersonMessage::MESSAGE_TYPE_CONTACT);

        $this->assertCount(1, $fake->sent);
        $sent = $fake->sent[0]['message'];

        $prefix = "You have a new Ranger Clubhouse msg from $from. Subject: ";
        $limit = RBS::SMS_LIMIT - (strlen($prefix) + 3);
        $expected = $prefix . substr($subject, 0, $limit) . '  ' . RBS::SMS_SUFFIX;

        $this->assertSame($expected, $sent);
    }
}
