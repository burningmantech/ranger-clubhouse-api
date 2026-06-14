<?php

namespace Tests\Feature;

use App\Exceptions\MoodleDownForMaintenanceException;
use App\Lib\Moodle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoodleTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // The Moodle constructor reads these (throwing if absent).
        $this->setting('MoodleDomain', 'https://moodle.test');
        $this->setting('MoodleToken', 'test-token');
        $this->setting('MoodleServiceName', 'clubhouse');
        $this->setting('MoodleStudentRoleID', '5');
    }

    /**
     * Http::fake() is the seam: Moodle's web-service calls run without a live
     * Moodle server, so the decode-and-filter behavior is exercisable.
     *
     * @return void
     */

    public function test_retrieve_available_courses_decodes_and_filters_site(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 1, 'format' => 'topics', 'fullname' => 'Online Training'],
            ['id' => 2, 'format' => 'site', 'fullname' => 'Site home'],
        ])]);

        $courses = (new Moodle())->retrieveAvailableCourses();

        $this->assertCount(1, $courses);
        $this->assertEquals(1, $courses[0]->id);
    }

    /**
     * A maintenance response surfaces as a typed exception — a failure path that
     * needs no live server to exercise.
     *
     * @return void
     */

    public function test_maintenance_response_throws(): void
    {
        Http::fake(['*' => Http::response('Site undergoing maintenance', 503)]);

        $this->expectException(MoodleDownForMaintenanceException::class);
        (new Moodle())->retrieveAvailableCourses();
    }
}
