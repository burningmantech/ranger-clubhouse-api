<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonLanguage;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PersonLanguageControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    /*
     * have each test have a fresh user that is logged in.
     */

    public function setUp(): void
    {
        parent::setUp();
        $this->signInUser();
    }

    /*
  * Test Languages Report
  */

    public function testLanguagesReport()
    {
        $this->addRole(Role::MANAGE);

        $personEnglish = Person::factory()->create(['on_site' => 1]);
        PersonLanguage::factory()->create([
            'person_id' => $personEnglish->id,
            'language_name' => 'English',
        ]);

        $personFrench = Person::factory()->create(['on_site' => 1]);
        PersonLanguage::factory()->create([
            'person_id' => $personFrench->id,
            'language_name' => 'French'
        ]);

        PersonLanguage::factory()->create([
            'person_id' => $personFrench->id,
            'language_name' => PersonLanguage::LANGUAGE_NAME_CUSTOM,
            'language_custom' => 'Spaghettise'
        ]);

        $response = $this->json('GET', 'person-language/on-site-report');
        $response->assertStatus(200);

        $response->assertJson([
            'languages' => [
                'common' => [
                    [
                        'name' => 'English',
                        'people' => [
                            [
                                'id' => $personEnglish->id,
                                'callsign' => $personEnglish->callsign
                            ]
                        ]
                    ],

                    [
                        'name' => 'French',
                        'people' => [
                            [
                                'id' => $personFrench->id,
                                'callsign' => $personFrench->callsign
                            ]
                        ]
                    ]
                ],
                'other' => [
                    [
                        'name' => 'Spaghettise',
                        'people' => [
                            [
                                'id' => $personFrench->id,
                                'callsign' => $personFrench->callsign
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }


}
