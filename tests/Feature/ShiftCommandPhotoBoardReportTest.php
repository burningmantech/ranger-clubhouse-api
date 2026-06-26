<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonPhoto;
use App\Models\Position;
use App\Models\Role;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCommandPhotoBoardReportTest extends TestCase
{
    use RefreshDatabase;

    private const string ROUTE = 'slot/shift-command-photo-board';

    public function setUp(): void
    {
        parent::setUp();

        $this->signInUser();

        $positions = [
            [Position::RSC_SHIFT_LEAD, 'Shift Lead'],
            [Position::TROUBLESHOOTER, 'Troubleshooter'],
            [Position::OPERATOR, 'Operator'],
            [Position::OPERATOR_SMOOTH, 'Operator (Smooth)'],
            [Position::OOD, 'Officer of the Day'],
            [Position::ROC_STAR, 'ROC Star'],
        ];

        foreach ($positions as [$id, $title]) {
            Position::factory()->create(['id' => $id, 'title' => $title]);
        }
    }

    private function putOnDuty(Person $person, int $positionId): void
    {
        Timesheet::factory()->create([
            'person_id' => $person->id,
            'position_id' => $positionId,
            'on_duty' => now(),
        ]);
    }

    private function approvePhoto(Person $person): void
    {
        $photo = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'image_filename' => 'headshot.jpg',
            'status' => PersonPhoto::APPROVED,
        ]);

        $person->person_photo_id = $photo->id;
        $person->saveWithoutValidation();
    }

    /**
     * The board is gated behind the Event Management role.
     */
    public function testRequiresEventManagementRole(): void
    {
        $this->json('GET', self::ROUTE)->assertStatus(403);
    }

    /**
     * On-duty personnel are bucketed under their group title and tagged with the
     * position they are working.
     */
    public function testGroupsOnDutyPersonnelUnderTitles(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $khaki = Person::factory()->create(['callsign' => 'Boss']);
        $this->putOnDuty($khaki, Position::RSC_SHIFT_LEAD);

        $ood = Person::factory()->create(['callsign' => 'Chief']);
        $this->putOnDuty($ood, Position::OOD);

        $response = $this->json('GET', self::ROUTE)->assertStatus(200);

        $this->assertSame(
            ['Khakis', 'Troubleshooters', 'Operators', 'OODs'],
            array_column($response->json('groups'), 'title')
        );

        $this->assertSame([
            'id' => $khaki->id,
            'callsign' => 'Boss',
            'photo_url' => null,
            'position' => ['id' => Position::RSC_SHIFT_LEAD, 'title' => 'Shift Lead'],
        ], $response->json('groups.0.people.0'));

        $this->assertSame('Chief', $response->json('groups.3.people.0.callsign'));
        $this->assertSame([], $response->json('groups.1.people'));
    }

    /**
     * Within a group, people are ordered by callsign case-insensitively even when
     * spread across multiple positions in that group.
     */
    public function testSortsCallsignsCaseInsensitivelyWithinGroup(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $this->putOnDuty(Person::factory()->create(['callsign' => 'zulu']), Position::OPERATOR);
        $this->putOnDuty(Person::factory()->create(['callsign' => 'Alpha']), Position::OPERATOR_SMOOTH);

        $operators = $this->json('GET', self::ROUTE)->assertStatus(200)->json('groups.2.people');

        $this->assertSame(['Alpha', 'zulu'], array_column($operators, 'callsign'));
    }

    /**
     * An empty board still returns the full object shape (now/hosts/groups), not a
     * bare array, so the frontend gets a consistent contract.
     */
    public function testEmptyBoardReturnsFullObject(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $response = $this->json('GET', self::ROUTE)->assertStatus(200);

        $response->assertJsonStructure(['now', 'hosts', 'groups' => [['title', 'people']]]);
        $this->assertSame([], $response->json('hosts'));
        $this->assertCount(4, $response->json('groups'));
        $this->assertSame([], $response->json('groups.0.people'));
    }

    /**
     * Signed-off shifts (off_duty set) are not on the board.
     */
    public function testIgnoresSignedOffShifts(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $person = Person::factory()->create(['callsign' => 'Gone']);
        Timesheet::factory()->create([
            'person_id' => $person->id,
            'position_id' => Position::OOD,
            'on_duty' => now()->subHours(3),
            'off_duty' => now()->subHour(),
        ]);

        $this->assertSame(
            [],
            $this->json('GET', self::ROUTE)->assertStatus(200)->json('groups.3.people')
        );
    }

    /**
     * ROC Star hosts are listed separately, with no position block.
     */
    public function testHostsListedSeparatelyWithoutPosition(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $host = Person::factory()->create(['callsign' => 'Helper']);
        $this->putOnDuty($host, Position::ROC_STAR);

        $response = $this->json('GET', self::ROUTE)->assertStatus(200);

        $this->assertSame([
            'id' => $host->id,
            'callsign' => 'Helper',
            'photo_url' => null,
        ], $response->json('hosts.0'));

        foreach ($response->json('groups') as $group) {
            $this->assertSame([], $group['people']);
        }
    }

    /**
     * photo_url resolves to the approved photo's url, or null when there is none.
     */
    public function testResolvesApprovedPhotoUrl(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $withPhoto = Person::factory()->create(['callsign' => 'Pose']);
        $this->approvePhoto($withPhoto);
        $this->putOnDuty($withPhoto, Position::OOD);

        $withoutPhoto = Person::factory()->create(['callsign' => 'Bare']);
        $this->putOnDuty($withoutPhoto, Position::OOD);

        $people = $this->json('GET', self::ROUTE)->assertStatus(200)->json('groups.3.people');

        $this->assertNull($people[0]['photo_url']);
        $this->assertNotEmpty($people[1]['photo_url']);
    }

    /**
     * A dangling person_photo_id (the photo row is gone) must not 500 the board.
     */
    public function testSurvivesOrphanedPhotoReference(): void
    {
        $this->addRole(Role::EVENT_MANAGEMENT);

        $person = Person::factory()->create(['callsign' => 'Ghost']);
        $person->person_photo_id = 999999;
        $person->saveWithoutValidation();
        $this->putOnDuty($person, Position::OOD);

        $this->assertNull(
            $this->json('GET', self::ROUTE)->assertStatus(200)->json('groups.3.people.0.photo_url')
        );
    }
}
