<?php

namespace Tests\Feature;

use App\Models\HandleReservation;
use App\Models\Person;
use Tests\TestCase;

class HandleReservationAttributeTest extends TestCase
{
    /**
     * The handle setter trims the value and writes it to the handle column.
     */
    public function test_handle_setter_trims_and_stores_handle(): void
    {
        $reservation = new HandleReservation;
        $reservation->handle = '  Hubcap  ';

        $this->assertSame('Hubcap', $reservation->getAttributes()['handle']);
    }

    /**
     * The handle setter also writes the normalized handle to normalized_handle.
     */
    public function test_handle_setter_writes_normalized_handle(): void
    {
        $reservation = new HandleReservation;
        $reservation->handle = '  Hub-Cap!  ';

        $this->assertSame(
            Person::normalizeCallsign('Hub-Cap!'),
            $reservation->getAttributes()['normalized_handle']
        );
    }

    /**
     * A null handle is coerced to an empty string and normalized accordingly.
     */
    public function test_handle_setter_coerces_null_to_blank(): void
    {
        $reservation = new HandleReservation;
        $reservation->handle = null;

        $this->assertSame('', $reservation->getAttributes()['handle']);
        $this->assertSame('', $reservation->getAttributes()['normalized_handle']);
    }
}
