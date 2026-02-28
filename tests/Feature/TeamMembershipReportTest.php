<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonPosition;
use App\Models\PersonTeam;
use App\Models\Position;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamMembershipReportTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::ADMIN);
    }

    /**
     * Test that position grants include joined_on and left_on from person_position_log.
     */
    public function testPositionGrantIncludesJoinedOnFromLog(): void
    {
        $team = Team::factory()->create();
        $position = Position::factory()->create([
            'team_id' => $team->id,
            'team_category' => Position::TEAM_CATEGORY_ALL_MEMBERS,
        ]);

        $member = Person::factory()->create();
        PersonTeam::factory()->create(['person_id' => $member->id, 'team_id' => $team->id]);
        PersonPosition::factory()->create(['person_id' => $member->id, 'position_id' => $position->id]);

        DB::table('person_position_log')->insert([
            'person_id' => $member->id,
            'position_id' => $position->id,
            'joined_on' => '2024-06-15',
            'left_on' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->json('GET', "team/{$team->id}/membership");
        $response->assertStatus(200);

        $people = $response->json('people');
        $person = collect($people)->firstWhere('id', $member->id);
        $this->assertNotNull($person);

        $positionData = collect($person['positions'])->firstWhere('id', $position->id);
        $this->assertNotNull($positionData);
        $this->assertEquals('2024-06-15', $positionData['joined_on']);
        $this->assertNull($positionData['left_on']);
    }

    /**
     * Test that position grants without a log record have null joined_on and left_on.
     */
    public function testPositionGrantWithoutLogHasNullDates(): void
    {
        $team = Team::factory()->create();
        $position = Position::factory()->create([
            'team_id' => $team->id,
            'team_category' => Position::TEAM_CATEGORY_ALL_MEMBERS,
        ]);

        $member = Person::factory()->create();
        PersonTeam::factory()->create(['person_id' => $member->id, 'team_id' => $team->id]);
        PersonPosition::factory()->create(['person_id' => $member->id, 'position_id' => $position->id]);

        $response = $this->json('GET', "team/{$team->id}/membership");
        $response->assertStatus(200);

        $people = $response->json('people');
        $person = collect($people)->firstWhere('id', $member->id);
        $this->assertNotNull($person);

        $positionData = collect($person['positions'])->firstWhere('id', $position->id);
        $this->assertNotNull($positionData);
        $this->assertNull($positionData['joined_on']);
        $this->assertNull($positionData['left_on']);
    }
}
