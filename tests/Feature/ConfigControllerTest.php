<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testConfigResults()
    {
        $this->setting('GeneralSupportEmail', 'test@example.com');
        $response = $this->json('GET', 'config');
        $response->assertStatus(200);
        $response->assertJson([ 'GeneralSupportEmail' => 'test@example.com' ]);
    }
}
