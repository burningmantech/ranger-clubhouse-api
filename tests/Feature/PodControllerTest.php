<?php

namespace Tests\Feature;

use App\Models\PersonPod;
use App\Models\Pod;
use App\Models\Position;
use App\Models\Role;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PodControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
        $this->addRole(Role::MENTOR);
    }

    /**
     * Retrieve some pods
     */

    public function testIndex()
    {
        $pod = Pod::factory()->create(['type' => Pod::TYPE_ALPHA]);

        $response = $this->json('GET', 'pod');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'pod');
        $response->assertJson([
            'pod' => [[
                'id' => $pod->id
            ]]
        ]);
    }

    /**
     * Create a pod document
     */

    public function testCreatePod()
    {
        $slot = Slot::factory()->create([
            'begins' => date("Y-08-01 12:00:00"),
            'ends' => date('Y-08-02 12:00:00'),
            'position_id' => Position::ALPHA,
        ]);

        $data = [
            'type' => Pod::TYPE_MENTOR,
            'slot_id' => $slot->id,
            'sort_index' => 2,
        ];

        $response = $this->json('POST', 'pod', ['pod' => $data]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pod', $data);
    }

    /**
     * Update a pod document
     */

    public function testUpdatePod()
    {
        $pod = Pod::factory()->create();

        $response = $this->json('PUT', "pod/{$pod->id}", [
            'pod' => ['sort_index' => 100]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pod', ['id' => $pod->id, 'sort_index' => 100]);
    }

    /**
     * Delete a pod
     */

    public function testDeletePod()
    {
        $pod = Pod::factory()->create();
        $podId = $pod->id;

        PersonPod::factory()->create(['person_id' => 99999, 'pod_id' => $podId]);

        $response = $this->json('DELETE', "pod/{$podId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('pod', ['id' => $podId]);
        $this->assertDatabaseMissing('person_pod', ['pod_id' => $podId]);
    }

    /**
     * Test create an alpha set (mentor, mitten, and alpha -- all linked together.)
     * @return void
     */

    public function testCreateAlphaSet()
    {
        $slot = Slot::factory()->create([
            'begins' => date("Y-08-25 12:00:00"),
            'ends' => date('Y-08-25 16:00:00'),
            'position_id' => Position::ALPHA,
        ]);

        $response = $this->json('POST', "pod/create-alpha-set", ['slot_id' => $slot->id]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['alpha', 'mentor', 'mitten']);
        $alphaPod = $response['alpha'];
        $mentorPod = $response['mentor'];
        $mittenPod = $response['mitten'];

        $mentorPodId = $mentorPod['id'];
        $this->assertDatabaseHas('pod', ['id' => $mentorPodId, 'type' => Pod::TYPE_MENTOR, 'slot_id' => $slot->id]);
        $this->assertDatabaseHas('pod', ['id' => $mittenPod['id'], 'type' => Pod::TYPE_MITTEN, 'slot_id' => $slot->id, 'mentor_pod_id' => $mentorPodId]);
        $this->assertDatabaseHas('pod', ['id' => $alphaPod['id'], 'type' => Pod::TYPE_ALPHA, 'slot_id' => $slot->id, 'mentor_pod_id' => $mentorPodId]);
    }

    /**
     * Test adding a person to a pod.
     *
     * @return void
     */

    public function testAddPersonToPod()
    {
        $pod = Pod::factory()->create();

        $response = $this->json('POST', "pod/{$pod->id}/person", ['person_id' => $this->user->id]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('person_pod', ['pod_id' => $pod->id, 'person_id' => $this->user->id]);
    }

    /**
     * Test removing a person from a pod.
     *
     * @return void
     */

    public function testRemovePersonFromPod()
    {
        $pod = Pod::factory()->create();
        $pp = PersonPod::factory()->create(['pod_id' => $pod->id, 'person_id' => $this->user->id, 'removed_at' => null]);
        $response = $this->json('DELETE', "pod/{$pod->id}/person", ['person_id' => $this->user->id]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('person_pod', [
            'pod_id' => $pod->id,
            'person_id' => $this->user->id
        ]);

        $pp->refresh();
        $this->assertNotNull($pp->removed_at);
    }

    /**
     * Test updating a person in a pod.
     *
     * @return void
     */

    public function testUpdatePersonFromPod()
    {
        $pod = Pod::factory()->create();
        $pp = PersonPod::factory()->create([
            'pod_id' => $pod->id,
            'person_id' => $this->user->id,
            'removed_at' => null,
            'is_lead' => false,
        ]);
        $response = $this->json('PATCH', "pod/{$pod->id}/person", ['person_id' => $this->user->id, 'is_lead' => true]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('person_pod', [
            'pod_id' => $pod->id,
            'person_id' => $this->user->id,
            'is_lead' => true,
        ]);
    }
}
