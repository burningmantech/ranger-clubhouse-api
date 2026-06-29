<?php

namespace Tests\Feature;

use App\Lib\RedactDatabase;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedactDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * On the default (non super-redact) path, execute() must scrub real first and
     * last names and replace the password hash for every person.
     *
     * Regression: the password reset used to be gated behind $superRedact, so a plain
     * redaction left real (production) password hashes in place. It is now always reset.
     */
    public function testExecuteScrubsNamesAndPasswordOnDefaultPath(): void
    {
        $knownPasswordHash = password_hash('s3cret-real-password', Person::PASSWORD_ENCRYPTION);

        $person = Person::factory()->create([
            'callsign' => 'RealRanger',
            'status' => Person::ACTIVE,
            'first_name' => 'Realfirst',
            'last_name' => 'Reallast',
            'password' => $knownPasswordHash,
        ]);

        RedactDatabase::execute(current_year(), false);

        $person->refresh();

        // Real names are gone, replaced with the generated placeholders.
        $this->assertNotEquals('Realfirst', $person->first_name);
        $this->assertNotEquals('Reallast', $person->last_name);
        $this->assertEquals("First{$person->id}", $person->first_name);
        $this->assertEquals("Last{$person->id}", $person->last_name);

        // The real password hash must have been overwritten.
        $this->assertNotEquals($knownPasswordHash, $person->password);
        $this->assertTrue(password_verify('abcdef', $person->password));
    }

    /**
     * The redaction rewrites emails for ACTIVE/INACTIVE people from their (preserved)
     * normalized callsign. The GHD build looks these test accounts up by email, so the
     * callsign must survive and the derived email must resolve via findByEmail().
     */
    public function testExecutePreservesGhdTestAccountCallsignAndEmail(): void
    {
        // GHD build resolves "hqworkertest@nomail.none" via Person::findByEmail().
        $hqWorker = Person::factory()->create([
            'callsign' => 'hqworkertest',
            'status' => Person::ACTIVE,
            'email' => 'someone-real@example.com',
        ]);

        RedactDatabase::execute(current_year(), false);

        $hqWorker->refresh();

        // Callsign is untouched by redaction.
        $this->assertEquals('hqworkertest', $hqWorker->callsign);

        // Email is regenerated from the callsign, so the account stays findable.
        $this->assertEquals('hqworkertest@nomail.none', $hqWorker->email);
        $this->assertNotNull(Person::findByEmail('hqworkertest@nomail.none'));
        $this->assertEquals($hqWorker->id, Person::findByEmail('hqworkertest@nomail.none')->id);
    }

    /**
     * setupWESLTraining() (invoked by execute()) updates the 'Safety Phil' and 'keeper'
     * accounts by callsign. Redaction must leave their callsigns intact so they remain
     * findable, and apply the WESL-specific data.
     */
    public function testExecutePreservesWeslAccountsAndAppliesWeslData(): void
    {
        $safetyPhil = Person::factory()->create([
            'callsign' => 'Safety Phil',
            'status' => Person::ACTIVE,
        ]);

        $keeper = Person::factory()->create([
            'callsign' => 'keeper',
            'status' => Person::ACTIVE,
        ]);

        RedactDatabase::execute(current_year(), false);

        $safetyPhil->refresh();
        $keeper->refresh();

        // Callsigns survive, so the WESL accounts remain findable.
        $this->assertNotNull(Person::findByCallsign('Safety Phil'));
        $this->assertNotNull(Person::findByCallsign('keeper'));

        // WESL-specific data was applied.
        $this->assertEquals('DPW Ghetto', $safetyPhil->camp_location);
        $this->assertStringContainsString('Morticia Addams', $keeper->emergency_contact);
    }
}
