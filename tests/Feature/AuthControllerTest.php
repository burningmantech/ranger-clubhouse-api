<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Person;
use App\Models\Role;
use App\Mail\ResetPassword;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test for missing parameters
     */
    public function testMissingParameters()
    {
        $response = $this->json('POST', 'auth/login', [ ]);
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
            'password'   => 'noshowers',
             ]
        );

        $response->assertStatus(401);
        $response->assertJson([ 'status' => 'invalid-credentials']);
    }

    /**
     * test for disabled account
     */

    public function testDisabledPerson()
    {
        $user = factory(\App\Models\Person::class)->create([ 'user_authorized' => false]);

        $response = $this->json(
            'POST', 'auth/login',
            [ 'identification' => $user->email, 'password'   => 'ineedashower!' ]
        );

        $response->assertStatus(401);
        $response->assertJson([ 'status' => 'account-disabled' ]);
    }

    /**
     * test for a wrong password.
     */

    public function testWrongPassword()
    {
        $user = factory(\App\Models\Person::class)->create();

        $response = $this->json(
            'POST', 'auth/login',
            [ 'identification' => $user->email, 'password'   => 'ineedashower! maybe' ]
        );

        $response->assertStatus(401);
        $response->assertJson([ 'status' => 'invalid-credentials' ]);
    }

   /**
    * test for succesfull reset password
    */

    public function testResetPassword()
    {
        $user = factory(\App\Models\Person::class)->create();

        Mail::fake();

        $response = $this->json(
            'POST', 'auth/reset-password', [
            'identification' => $user->email,
                ]
        );

        $response->assertStatus(200);
        $response->assertJson([ 'status' => 'success' ]);

        Mail::assertSent(
            ResetPassword::class, function ($mail) use ($user) {
                    return $mail->hasTo($user->email);
            }
        );
    }

    /**
     * Test for failed reset password using non existant user
     */

    public function testResetPasswordForUnknownUser()
    {
        $user = factory(\App\Models\Person::class)->create();

        Mail::fake();

        $response = $this->json(
            'POST', 'auth/reset-password',
            [ 'identification' => 'whothehellami@nowhere.dev' ]
        );

        $response->assertStatus(400);
        $response->assertJson([ 'status' => 'not-found' ]);
        Mail::assertNothingSent();
    }

    /**
     * Test for failed reset password on disabled user
     */

    public function testResetPasswordForDisabledUser()
    {
        $user = factory(\App\Models\Person::class)->create([ 'user_authorized' => false]);

        Mail::fake();

        $response = $this->json(
            'POST', 'auth/reset-password',
            [ 'identification' => $user->email ]
        );

        $response->assertStatus(403);
        $response->assertJson([ 'status' => 'account-disabled' ]);
        Mail::assertNothingSent();
    }

}
