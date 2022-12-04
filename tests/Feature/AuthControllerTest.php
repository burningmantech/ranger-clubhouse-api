<?php

namespace Tests\Feature;

use App\Lib\Agreements;
use App\Mail\ResetPassword;
use App\Models\Document;
use App\Models\Person;
use App\Models\Role;
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

    /**
     * Test to ensure roles are retained when the NDA does not exist.
     *
     * @return void
     */
    public function testRolesNDADoesNotExist(): void
    {
        $this->signInUser();
        $this->addRole(Role::MANAGE);
        $person = Person::factory()->create();

        // Ensure the user can retain LM if the NDA doesn't exist.
        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
    }

    /**
     * Test to ensure roles are nuked when the NDA exists and has not been signed.
     *
     * @return void
     */

    public function testsRolesNDAExists(): void
    {
        $this->signInUser();
        $this->addRole(Role::MANAGE);
        $person = Person::factory()->create();

        // Ensure the LM is revoked when the NDA is present and has not agreed to the doc.
        Document::factory()->create(['tag' => Agreements::DEPT_NDA, 'description' => 'Dept NDA', 'body' => 'Do no evil']);
        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(403);
    }

    /**
     * Test to ensure roles are nuked when the NDA exists and has not been signed.
     *
     * @return void
     */

    public function testsRolesAndNDASigned(): void
    {
        $this->signInUser();
        $this->addRole(Role::MANAGE);
        $person = Person::factory()->create();

        // Ensure the LM is revoked when the NDA is present and has not agreed to the doc.
        Document::factory()->create(['tag' => Agreements::DEPT_NDA, 'description' => 'Dept NDA', 'body' => 'Do no evil']);
        // Sign the NDA
        Agreements::signAgreement($this->user, Agreements::DEPT_NDA, 1);

        $response = $this->json('GET', "person/{$person->id}");
        $response->assertStatus(200);
    }
}
