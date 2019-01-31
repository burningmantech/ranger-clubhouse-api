<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConfigControllerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testConfigResults()
    {
        config([ 'client.sendToClient' => 'the value' ]);
        config([ 'clubhouse.dontSendToClient' => 'a secret' ]);

        $response = $this->json('GET', 'config');

        $response->assertStatus(200);
        $response->assertJson([ 'sendToClient' => 'the value' ]);
        $response->assertJsonMissing([ 'dontSendToClient' => 'a secret' ]);
    }
}
