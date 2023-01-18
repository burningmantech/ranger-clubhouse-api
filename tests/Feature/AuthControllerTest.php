<?php

namespace Tests\Feature;

use App\Mail\ResetPassword;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test for missing parameters
     */
    public function testMissingParameters()
    {
        $response = $this->json('POST', 'auth/login', []);
        $response->assertStatus(422);
    }

    /**
     * test for unknown person
     */

    public function testUnknownPerson()
    {
        $response = $this->json(
            'POST', 'auth/login', [
                'identification' => 'no-such-user@example.com',
                'password' => 'noshowers',
            ]
        );

        $response->assertStatus(401);
        $response->assertJson(['status' => 'invalid-credentials']);
    }

    /**
     * test for suspended account
     */

    public function testSuspendedPerson()
    {
        $user = Person::factory()->create(['status' => 'suspended']);

        $response = $this->json(
            'POST', 'auth/login',
            ['identification' => $user->email, 'password' => 'ineedashower!']
        );

        $response->assertStatus(401);
        $response->assertJson(['status' => 'account-suspended']);
    }


    /**
     * test for a wrong password.
     */

    public function testWrongPassword()
    {
        $user = Person::factory()->create();

        $response = $this->json(
            'POST', 'auth/login',
            ['identification' => $user->email, 'password' => 'ineedashower! maybe']
        );

        $response->assertStatus(401);
        $response->assertJson(['status' => 'invalid-credentials']);
    }

    /**
     * test for successful reset password
     */

    public function testResetPassword()
    {
        $user = Person::factory()->create();

        Mail::fake();

        $response = $this->json('POST', 'auth/reset-password', ['identification' => $user->email]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        Mail::assertSent(ResetPassword::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test for failed reset password using non existent user
     */

    public function testResetPasswordForUnknownUser()
    {
        $user = Person::factory()->create();

        Mail::fake();

        $response = $this->json(
            'POST', 'auth/reset-password',
            ['identification' => 'whothehellami@nowhere.dev']
        );

        $response->assertStatus(400);
        $response->assertJson(['status' => 'not-found']);
        Mail::assertNothingSent();
    }

    /**
     * Test for failed reset password on disabled user
     */

    public function testResetPasswordForDisabledUser()
    {
        $user = Person::factory()->create(['status' => Person::SUSPENDED]);

        Mail::fake();

        $response = $this->json(
            'POST', 'auth/reset-password',
            ['identification' => $user->email]
        );

        $response->assertStatus(403);
        $response->assertJson(['status' => 'account-disabled']);
        Mail::assertNothingSent();
    }
}
