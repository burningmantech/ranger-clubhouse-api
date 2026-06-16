<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonAward;
use App\Models\PersonTeam;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInAsAdmin();
    }

    /**
     * Deleting a team must cascade-delete its PersonAward rows (matched by team_id).
     * Regression: the deleted() hook previously matched person_award.id against the team id.
     */
    public function testTeamDeletionRemovesAssociatedAwards(): void
    {
        $person = Person::factory()->create();

        // Advance the team id sequence so the team id differs from the award id; this makes the
        // regression unambiguous (the old hook matched person_award.id against the team id).
        Team::factory()->create();
        $team = Team::factory()->create();
        $teamAward = PersonAward::factory()->create([
            'person_id' => $person->id,
            'team_id' => $team->id,
            'year' => 2024,
        ]);

        // Sanity check: under the old hook (matching person_award.id against the team id) this
        // award survived, because its id does not equal the team id.
        $this->assertNotEquals($team->id, $teamAward->id);

        $team->delete();

        $this->assertNull(PersonAward::find($teamAward->id), 'The deleted team\'s award should be removed.');
        $this->assertSame(0, PersonAward::where('team_id', $team->id)->count(), 'No award rows for the team may remain.');
    }

    /**
     * Showing a team projects the role_ids pseudo column from its team_roles.
     */
    public function testShowReturnsRoleIds(): void
    {
        $team = Team::factory()->create();
        TeamRole::factory()->create(['team_id' => $team->id, 'role_id' => Role::EVENT_MANAGEMENT]);

        $response = $this->json('GET', "team/{$team->id}");

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing([Role::EVENT_MANAGEMENT], $response->json('team.role_ids'));
    }

    /**
     * The team index can include role_ids for each team without throwing.
     */
    public function testIndexCanIncludeRoleIds(): void
    {
        $team = Team::factory()->create();
        TeamRole::factory()->create(['team_id' => $team->id, 'role_id' => Role::EVENT_MANAGEMENT]);

        $response = $this->json('GET', 'team', ['include_roles' => 1]);

        $response->assertStatus(200);
        $row = collect($response->json('team'))->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertEqualsCanonicalizing([Role::EVENT_MANAGEMENT], $row['role_ids']);
    }

    /**
     * Filling the role_ids pseudo column routes the value onto the public $role_ids property
     * (it is not a real team column), and serialization projects it back out. This guards the
     * Attribute get/set bridge against the magic-property shadowing of the public field.
     */
    public function testRoleIdsFillBridgesToPublicPropertyAndSerializes(): void
    {
        $team = new Team();
        $team->fill(['role_ids' => [Role::EVENT_MANAGEMENT, Role::MENTOR]]);

        $this->assertEqualsCanonicalizing(
            [Role::EVENT_MANAGEMENT, Role::MENTOR],
            $team->role_ids,
            'fill() must populate the public $role_ids property via the mutator.'
        );
        $this->assertArrayNotHasKey(
            'role_ids',
            $team->getAttributes(),
            'role_ids must not leak into the real attribute bag; it is a pseudo column.'
        );

        $team->append('role_ids');
        $this->assertEqualsCanonicalizing(
            [Role::EVENT_MANAGEMENT, Role::MENTOR],
            $team->toArray()['role_ids'],
            'Serialization must project the public $role_ids via the accessor.'
        );
    }

    /**
     * Granting membership to a team carrying the Admin/Tech Ninja role is blocked.
     */
    public function testBulkGrantBlockedForRestrictedTeam(): void
    {
        $team = Team::factory()->create();
        TeamRole::factory()->create(['team_id' => $team->id, 'role_id' => Role::ADMIN]);
        $target = Person::factory()->create();

        $response = $this->json('POST', "team/{$team->id}/bulk-grant-revoke", [
            'callsigns' => $target->callsign,
            'grant' => true,
            'commit' => true,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Revoking membership from a team carrying the Admin/Tech Ninja role is blocked too.
     * Regression: only the grant direction used to be guarded.
     */
    public function testBulkRevokeBlockedForRestrictedTeam(): void
    {
        $team = Team::factory()->create();
        TeamRole::factory()->create(['team_id' => $team->id, 'role_id' => Role::ADMIN]);
        $target = Person::factory()->create();
        PersonTeam::factory()->create(['person_id' => $target->id, 'team_id' => $team->id]);

        $response = $this->json('POST', "team/{$team->id}/bulk-grant-revoke", [
            'callsigns' => $target->callsign,
            'grant' => false,
            'commit' => true,
        ]);

        $response->assertStatus(403);
        $this->assertTrue(PersonTeam::haveTeam($team->id, $target->id), 'Membership must remain after a blocked revoke.');
    }

    /**
     * Bulk grant works for an ordinary team and reports success per callsign.
     */
    public function testBulkGrantSucceedsForOrdinaryTeam(): void
    {
        $team = Team::factory()->create();
        $target = Person::factory()->create();

        $response = $this->json('POST', "team/{$team->id}/bulk-grant-revoke", [
            'callsigns' => $target->callsign,
            'grant' => true,
            'commit' => true,
        ]);

        $response->assertStatus(200);
        $result = collect($response->json('people'))->firstWhere('id', $target->id);
        $this->assertNotNull($result);
        $this->assertTrue($result['success'] ?? false);
        $this->assertTrue(PersonTeam::haveTeam($team->id, $target->id));
    }

    /**
     * PersonTeam::removePerson refuses to revoke from a Tech Ninja team for a non Tech Ninja user.
     */
    public function testRemovePersonGuardsRestrictedTeam(): void
    {
        $team = Team::factory()->create();
        TeamRole::factory()->create(['team_id' => $team->id, 'role_id' => Role::TECH_NINJA]);
        $target = Person::factory()->create();
        PersonTeam::factory()->create(['person_id' => $target->id, 'team_id' => $team->id]);

        $this->expectException(AuthorizationException::class);

        PersonTeam::removePerson($team->id, $target->id, 'test revoke');
    }
}
