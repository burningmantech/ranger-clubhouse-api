<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $this->setting('AdminEmail', 'test@example.com');
        $response = $this->json('GET', 'config');
        $response->assertStatus(200);
        $response->assertJson(['AdminEmail' => 'test@example.com']);
    }
}
