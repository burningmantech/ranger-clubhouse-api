<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PersonPhotoRetrieveExpiredPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * An archived photo belonging to a person with no current photo set
     * (person.person_photo_id IS NULL) must still be expired. A plain
     * whereColumn('person.person_photo_id', '!=', 'person_photo.id')
     * excludes these rows because `NULL != id` is NULL, not true, in SQL.
     */

    public function test_expires_archived_photo_when_person_has_no_current_photo(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 7, 12, 0, 0));

        $person = Person::factory()->create([
            'status' => Person::PAST_PROSPECTIVE,
            'person_photo_id' => null,
        ]);

        $photo = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::REJECTED,
            'created_at' => '2020-01-01 00:00:00',
        ]);

        $expired = PersonPhoto::retrieveExpiredPhotos();

        $this->assertTrue($expired->contains('id', $photo->id));
    }

    /**
     * The expiration cutoff uses subMonthsNoOverflow() so that month-end dates
     * don't overflow into the following month. With a plain subMonths(6) from
     * 2026-08-31, the cutoff rolls over to 2026-03-03 instead of the intended
     * 2026-02-28, which would incorrectly expire a photo created on 2026-03-01.
     */

    public function test_does_not_expire_photo_created_after_no_overflow_cutoff(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 12, 0, 0));

        $person = Person::factory()->create([
            'status' => Person::PAST_PROSPECTIVE,
        ]);

        $currentPhoto = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::APPROVED,
        ]);
        $person->person_photo_id = $currentPhoto->id;
        $person->saveWithoutValidation();

        $archivedPhoto = PersonPhoto::factory()->create([
            'person_id' => $person->id,
            'status' => PersonPhoto::REJECTED,
            'created_at' => '2026-03-01 00:00:00',
        ]);

        $expired = PersonPhoto::retrieveExpiredPhotos();

        $this->assertFalse($expired->contains('id', $archivedPhoto->id));
    }
}
