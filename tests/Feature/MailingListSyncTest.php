<?php

namespace Tests\Feature;

use App\Jobs\MailingListSyncJob;
use App\Lib\GoogleGroups;
use App\Mail\MailingListChangesMail;
use App\Models\Person;
use App\Models\PersonTeam;
use App\Models\Team;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailingListSyncTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdmin();
        $this->setting('GoogleGroupsSyncEnabled', true);
        $this->setting('MailingListUpdateRequestEmail', 'rangers@example.com');
    }

    private function createCadre(string $email = 'ranger-greendot@burningman.org', string $type = Team::TYPE_CADRE): Team
    {
        return Team::factory()->create(['type' => $type, 'email' => $email]);
    }

    private function assertJobOperations(array $expected): void
    {
        Queue::assertPushed(MailingListSyncJob::class, fn($job) => $job->operations == $expected);
    }

    /**
     * Changing an active Ranger's email swaps the address in Allcom, Announce, and their cadre lists.
     */

    public function testEmailChangeUpdatesGlobalAndCadreLists(): void
    {
        Queue::fake([MailingListSyncJob::class]);

        $cadre = $this->createCadre();
        $contactOnly = $this->createCadre('contact@example.com');
        $plainTeam = $this->createCadre('ranger-plainteam@burningman.org', Team::TYPE_TEAM);
        $person = Person::factory()->create(['status' => Person::ACTIVE, 'email' => 'old@example.com']);
        foreach ([$cadre, $contactOnly, $plainTeam] as $team) {
            PersonTeam::factory()->create(['person_id' => $person->id, 'team_id' => $team->id]);
        }

        $response = $this->json('PUT', "person/{$person->id}", ['person' => ['email' => 'new@example.com']]);
        $response->assertStatus(200);

        $ops = [];
        foreach ([GoogleGroups::ALLCOM, GoogleGroups::ANNOUNCE, 'ranger-greendot@burningman.org'] as $group) {
            $ops[] = ['action' => 'remove', 'group' => $group, 'email' => 'old@example.com'];
            $ops[] = ['action' => 'add', 'group' => $group, 'email' => 'new@example.com'];
        }
        Queue::assertPushed(MailingListSyncJob::class, 1);
        $this->assertJobOperations($ops);
    }

    /**
     * Prospectives are not on the Ranger lists.
     */

    public function testEmailChangeIgnoredForProspective(): void
    {
        Queue::fake([MailingListSyncJob::class]);
        Mail::fake();
        $this->setting('VCEmail', 'vc@example.com');

        $person = Person::factory()->create(['status' => Person::PROSPECTIVE]);
        $this->json('PUT', "person/{$person->id}", ['person' => ['email' => 'new@example.com']])->assertStatus(200);

        Queue::assertNotPushed(MailingListSyncJob::class);
    }

    /**
     * Nothing happens when the sync is disabled.
     */

    public function testNothingDispatchedWhenDisabled(): void
    {
        Queue::fake([MailingListSyncJob::class]);
        $this->setting('GoogleGroupsSyncEnabled', false);

        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        $this->json('PUT', "person/{$person->id}", ['person' => ['email' => 'new@example.com']])->assertStatus(200);
        PersonTeam::addPerson($this->createCadre()->id, $person->id, 'test');

        Queue::assertNotPushed(MailingListSyncJob::class);
    }

    /**
     * Adding and removing someone from a cadre updates the cadre's list.
     */

    public function testTeamAddAndRemoveUpdatesCadreList(): void
    {
        Queue::fake([MailingListSyncJob::class]);

        $cadre = $this->createCadre();
        $person = Person::factory()->create(['status' => Person::ACTIVE, 'email' => 'ranger@example.com']);

        $this->json('POST', "team/{$cadre->id}/bulk-grant-revoke", [
            'callsigns' => $person->callsign,
            'grant' => true,
            'commit' => true,
        ])->assertStatus(200);

        $this->assertJobOperations([
            ['action' => 'add', 'group' => 'ranger-greendot@burningman.org', 'email' => 'ranger@example.com']
        ]);

        $this->json('POST', "person/{$person->id}/teams", ['revoke_ids' => [$cadre->id]])->assertStatus(200);
        $this->assertFalse(PersonTeam::haveTeam($cadre->id, $person->id));

        $this->assertJobOperations([
            ['action' => 'remove', 'group' => 'ranger-greendot@burningman.org', 'email' => 'ranger@example.com']
        ]);
        Queue::assertPushed(MailingListSyncJob::class, 2);
    }

    /**
     * Delegations are synced, but plain teams and teams without a Ranger group address are not.
     */

    public function testOnlyCadresAndDelegationsWithGroupsAreSynced(): void
    {
        Queue::fake([MailingListSyncJob::class]);

        $person = Person::factory()->create(['status' => Person::ACTIVE]);
        PersonTeam::addPerson($this->createCadre('ranger-team@burningman.org', Team::TYPE_TEAM)->id, $person->id, 'test');
        PersonTeam::addPerson($this->createCadre('greendot@example.com')->id, $person->id, 'test');
        PersonTeam::addPerson($this->createCadre('')->id, $person->id, 'test');
        Queue::assertNotPushed(MailingListSyncJob::class);

        PersonTeam::addPerson($this->createCadre('ranger-delegation@burningman.org', Team::TYPE_DELEGATION)->id, $person->id, 'test');
        Queue::assertPushed(MailingListSyncJob::class, 1);
    }

    public function testIsManagedGroup(): void
    {
        $this->assertTrue(GoogleGroups::isManagedGroup('ranger-allcom@burningman.org'));
        $this->assertTrue(GoogleGroups::isManagedGroup('rangers-announce@burningman.org'));
        $this->assertTrue(GoogleGroups::isManagedGroup('Ranger-GreenDot@BurningMan.org'));
        $this->assertFalse(GoogleGroups::isManagedGroup('rangers@burningman.org'));
        $this->assertFalse(GoogleGroups::isManagedGroup('ranger-allcom@example.com'));
        $this->assertFalse(GoogleGroups::isManagedGroup('ranger-allcom@burningman.org.evil.com'));
        $this->assertFalse(GoogleGroups::isManagedGroup(null));
    }

    /**
     * The job applies each operation, keeps going after failures, and sends a confirmation email.
     */

    public function testJobAppliesChangesAndSendsConfirmation(): void
    {
        Mail::fake();

        $fake = new class extends GoogleGroups {
            public array $calls = [];

            public function addMember(string $group, string $email): string
            {
                $this->calls[] = ['add', $group, $email];
                if ($group == GoogleGroups::ANNOUNCE) {
                    throw new GoogleServiceException('{"error": {"code": 403}}', 403, null, [['message' => 'Not Authorized to access this resource/api']]);
                }
                return GoogleGroups::ADDED;
            }

            public function removeMember(string $group, string $email): string
            {
                $this->calls[] = ['remove', $group, $email];
                return GoogleGroups::NOT_FOUND;
            }
        };
        $this->app->instance(GoogleGroups::class, $fake);

        $person = Person::factory()->create(['status' => Person::ACTIVE, 'email' => 'new@example.com']);
        $ops = [
            ['action' => 'remove', 'group' => GoogleGroups::ALLCOM, 'email' => 'old@example.com'],
            ['action' => 'add', 'group' => GoogleGroups::ALLCOM, 'email' => 'new@example.com'],
            ['action' => 'add', 'group' => GoogleGroups::ANNOUNCE, 'email' => 'new@example.com'],
        ];

        MailingListSyncJob::dispatchSync($person, 'email changed', $ops, $this->user->id);

        $this->assertEquals([
            ['remove', GoogleGroups::ALLCOM, 'old@example.com'],
            ['add', GoogleGroups::ALLCOM, 'new@example.com'],
            ['add', GoogleGroups::ANNOUNCE, 'new@example.com'],
        ], $fake->calls);

        Mail::assertQueued(MailingListChangesMail::class, function ($mail) use ($person) {
            return $mail->person->id == $person->id
                && $mail->hasTo('rangers@example.com')
                && $mail->results[0]['success'] && $mail->results[0]['status'] == GoogleGroups::NOT_FOUND
                && $mail->results[1]['success'] && $mail->results[1]['status'] == GoogleGroups::ADDED
                && !$mail->results[2]['success']
                && $mail->results[2]['status'] == 'Not Authorized to access this resource/api (403)';
        });

        $this->assertDatabaseHas('action_logs', ['event' => 'mailing-list-sync', 'target_person_id' => $person->id]);
    }

    /**
     * The retired request endpoint no longer sends anything.
     */

    public function testUpdateMailingListsEndpointIsNoOp(): void
    {
        Mail::fake();
        $person = Person::factory()->create(['status' => Person::ACTIVE]);

        $this->json('POST', "contact/{$person->id}/update-mailing-lists", ['old_email' => 'old@example.com'])
            ->assertStatus(200);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }
}
