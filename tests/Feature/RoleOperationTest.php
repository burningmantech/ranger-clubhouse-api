<?php

namespace Tests\Feature;

use App\Lib\Agreements;
use App\Models\Document;
use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonRole;
use App\Models\PersonSlot;
use App\Models\PersonTeam;
use App\Models\Position;
use App\Models\PositionRole;
use App\Models\Role;
use App\Models\Slot;
use App\Models\Team;
use App\Models\TeamRole;
use App\Models\TraineeStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RoleOperationTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /**
     * Ensure the roles were cached properly.
     *
     * @return void
     */

    public function test_basic_role_caching(): void
    {
        $this->addRole([Role::VC, Role::MENTOR]);
        $response = $this->get('/person/' . $this->user->id);
        $response->assertStatus(200);

        $cachedRoles = PersonRole::getCache($this->user->id);
        $this->assertNotNull($cachedRoles);

        list ($effectiveRoles, $trueRoles) = $cachedRoles;

        $this->assertCount(2, $effectiveRoles);
        $this->assertContains(Role::VC, $effectiveRoles);
        $this->assertContains(Role::MENTOR, $effectiveRoles);

        $this->assertCount(2, $trueRoles);
        $this->assertContains(Role::VC, $trueRoles);
        $this->assertContains(Role::MENTOR, $trueRoles);
    }

    /**
     * Test to ensure roles are retained when the NDA does not exist.
     *
     * @return void
     */
    public function test_roles_are_retained_when_nda_does_not_exist(): void
    {
        $this->signInUser();
        $this->addRole(Role::EVENT_MANAGEMENT);
        $person = Person::factory()->create();

        // Ensure the user can retain LM if the NDA doesn't exist.
        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
    }

    /**
     * Test to ensure roles are nuked when the NDA exists and has not been signed.
     *
     * @return void
     */

    public function test_roles_are_nuked_when_nda_is_not_signed(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);
        $person = Person::factory()->create();

        // Ensure the LM is revoked when the NDA is present and has not agreed to the doc.
        Document::factory()->create(['tag' => Document::DEPT_NDA_TAG, 'description' => 'Dept NDA', 'body' => 'Do no evil']);
        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(403);
    }

    /**
     * Test to ensure roles are kept when the NDA exists, has not been signed and the user has the Tech Ninja role
     * (safety backup in case something gets hosed and the tech team needs to fix things)
     *
     * @return void
     */

    public function test_roles_are_kept_for_tech_ninjas(): void
    {
        $this->addRole([Role::TECH_NINJA, Role::EVENT_MANAGEMENT]);
        $person = Person::factory()->create();

        // Ensure the LM is revoked when the NDA is present and has not agreed to the doc.
        Document::factory()->create(['tag' => Document::DEPT_NDA_TAG, 'description' => 'Dept NDA', 'body' => 'Do no evil']);

        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
    }

    /**
     * Keep roles when the NDA has been signed
     *
     * @return void
     */

    public function test_roles_are_kept_when_NDA_is_signed(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);
        $person = Person::factory()->create();

        // Ensure the LM is revoked when the NDA is present and has not agreed to the doc.
        Document::factory()->create(['tag' => Document::DEPT_NDA_TAG, 'description' => 'Dept NDA', 'body' => 'Do no evil']);
        // Sign the NDA
        Agreements::signAgreement($this->user, Document::DEPT_NDA_TAG, 1);

        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
    }

    /**
     * Test associated position and team roles
     *
     * @return void
     */

    public function test_associated_roles(): void
    {
        $user = $this->user;

        $position = Position::factory()->create();
        PositionRole::factory()->create(['position_id' => $position->id, 'role_id' => Role::MENTOR]);
        PersonPosition::factory()->create(['person_id' => $user->id, 'position_id' => $position->id]);

        $team = Team::factory()->create();
        TeamRole::factory()->create(['role_id' => Role::EDIT_BMIDS, 'team_id' => $team->id]);
        PersonTeam::factory()->create(['person_id' => $user->id, 'team_id' => $team->id]);

        $user->retrieveRoles();
        $this->assertContains(Role::MENTOR, $user->roles);
        $this->assertContains(Role::EDIT_BMIDS, $user->roles);
    }

    /**
     * Test associated position roles that requires training
     *
     * @return void
     */

    public function test_requiring_training_for_associated_position_roles(): void
    {
        $user = $this->user;

        $training = Position::factory()->create(['type' => Position::TYPE_TRAINING]);
        $position = Position::factory()->create([
            'require_training_for_roles' => true, 'training_position_id' => $training->id
        ]);
        PositionRole::factory()->create(['position_id' => $position->id, 'role_id' => Role::MENTOR]);
        PersonPosition::factory()->create(['person_id' => $user->id, 'position_id' => $position->id]);
        $slot = Slot::factory()->create([
            'position_id' => $training->id,
            'begins' => now(),
            'ends' => now()->addMinute(1),
            'description' => 'a training',
            'max' => 2
        ]);

        $user->retrieveRoles();
        $this->assertNotContains(Role::MENTOR, $user->roles);

        PersonSlot::factory()->create(['person_id' => $user->id, 'slot_id' => $slot->id]);
        TraineeStatus::factory()->create(['slot_id' => $slot->id, 'person_id' => $user->id, 'passed' => true]);
        $user->roles = null; // Cause the roles to be reloaded
        Cache::flush();
        $user->retrieveRoles();
        $this->assertContains(Role::MENTOR, $user->roles);
    }

    /**
     * A position conferring only ordinary roles may be granted by anyone.
     *
     * @return void
     */

    public function test_grants_non_protected_position_without_special_role(): void
    {
        $position = Position::factory()->create();
        PositionRole::factory()->create(['position_id' => $position->id, 'role_id' => Role::MENTOR]);
        $target = Person::factory()->create();

        PersonPosition::addIdsToPerson($target->id, [$position->id], 'test');

        $this->assertDatabaseHas('person_position', ['person_id' => $target->id, 'position_id' => $position->id]);
    }

    /**
     * A position conferring the Tech Ninja role may only be granted by a Tech Ninja.
     *
     * @return void
     */

    public function test_cannot_grant_tech_ninja_position_without_tech_ninja(): void
    {
        $position = Position::factory()->create();
        PositionRole::factory()->create(['position_id' => $position->id, 'role_id' => Role::TECH_NINJA]);
        $target = Person::factory()->create();

        $this->expectException(AuthorizationException::class);
        PersonPosition::addIdsToPerson($target->id, [$position->id], 'test');
    }

    /**
     * A Tech Ninja may grant a position conferring the Tech Ninja role.
     *
     * @return void
     */

    public function test_tech_ninja_can_grant_tech_ninja_position(): void
    {
        $this->addRole(Role::TECH_NINJA);
        $position = Position::factory()->create();
        PositionRole::factory()->create(['position_id' => $position->id, 'role_id' => Role::TECH_NINJA]);
        $target = Person::factory()->create();

        PersonPosition::addIdsToPerson($target->id, [$position->id], 'test');

        $this->assertDatabaseHas('person_position', ['person_id' => $target->id, 'position_id' => $position->id]);
    }

    /**
     * Tech Ninja does not stand in for Admin: an Admin-conferring position still requires Admin.
     *
     * @return void
     */

    public function test_admin_position_requires_admin_not_tech_ninja(): void
    {
        $this->addRole(Role::TECH_NINJA); // godlike, but not an Admin
        $position = Position::factory()->create();
        PositionRole::factory()->create(['position_id' => $position->id, 'role_id' => Role::ADMIN]);
        $target = Person::factory()->create();

        $this->expectException(AuthorizationException::class);
        PersonPosition::addIdsToPerson($target->id, [$position->id], 'test');
    }

    /**
     * A team conferring the Tech Ninja role may only be granted by a Tech Ninja.
     *
     * @return void
     */

    public function test_cannot_grant_tech_ninja_team_without_tech_ninja(): void
    {
        $team = Team::factory()->create();
        TeamRole::factory()->create(['team_id' => $team->id, 'role_id' => Role::TECH_NINJA]);
        $target = Person::factory()->create();

        $this->expectException(AuthorizationException::class);
        PersonTeam::addPerson($team->id, $target->id, 'test');
    }

    /**
     * The role editor silently ignores a Tech Ninja grant attempted by a non-Tech-Ninja admin,
     * while still applying ordinary roles.
     *
     * @return void
     */

    public function test_update_roles_ignores_tech_ninja_grant_by_non_tech_ninja_admin(): void
    {
        $this->addRole(Role::ADMIN); // can reach the role editor, but is not a Tech Ninja
        $target = Person::factory()->create();

        $response = $this->json('POST', "person/{$target->id}/roles", [
            'role_ids' => [Role::MENTOR, Role::TECH_NINJA],
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('person_role', ['person_id' => $target->id, 'role_id' => Role::MENTOR]);
        $this->assertDatabaseMissing('person_role', ['person_id' => $target->id, 'role_id' => Role::TECH_NINJA]);
    }

    /**
     * An Admin may grant the Admin role through the role editor (behavior preserved).
     *
     * @return void
     */

    public function test_update_roles_allows_admin_to_grant_admin_role(): void
    {
        $this->addRole(Role::ADMIN);
        $target = Person::factory()->create();

        $response = $this->json('POST', "person/{$target->id}/roles", [
            'role_ids' => [Role::ADMIN],
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('person_role', ['person_id' => $target->id, 'role_id' => Role::ADMIN]);
    }
}
