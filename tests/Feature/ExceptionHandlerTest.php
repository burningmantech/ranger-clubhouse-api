<?php

namespace Tests\Feature;

use App\Http\Controllers\ConfigController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use RuntimeException;

class ExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    /*
    * have each test have a fresh user that is logged in.
    */

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    public function test_exception_handling()
    {
        $this->mock(ConfigController::class, function ($mock) {
            $mock->makePartial();
            $mock->shouldReceive('show')
                ->andThrow(new RuntimeException);
        });

        $response = $this->json('GET', 'config');
        $response->assertStatus(500);
        $this->assertDatabaseHas('error_logs', ['error_type' => 'server-exception']);
    }
}