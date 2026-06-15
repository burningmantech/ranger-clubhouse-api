<?php

namespace Tests\Feature;

use App\Models\TimesheetMissing;
use Tests\TestCase;

class TimesheetMissingAttributeTest extends TestCase
{
    /**
     * The duration accessor returns the number of seconds between on_duty and off_duty.
     */
    public function test_duration_accessor_returns_seconds_between_shifts(): void
    {
        $missing = new TimesheetMissing();
        $missing->setRawAttributes([
            'on_duty' => '2026-01-01 10:00:00',
            'off_duty' => '2026-01-01 12:30:00',
        ], true);

        $this->assertSame(9000, $missing->duration);
    }

    /**
     * The partner_info accessor returns null when no partner is provided.
     */
    public function test_partner_info_accessor_returns_null_for_empty_partner(): void
    {
        $missing = new TimesheetMissing(['partner' => '']);

        $this->assertNull($missing->partner_info);
    }

    /**
     * The partner_info accessor returns null for "not applicable" style partner values.
     */
    public function test_partner_info_accessor_returns_null_for_not_applicable_partner(): void
    {
        foreach (['na', 'n/a', 'none', 'no partner'] as $value) {
            $missing = new TimesheetMissing(['partner' => $value]);
            $this->assertNull($missing->partner_info, "Expected null for partner '{$value}'");
        }
    }

    /**
     * The time_warnings accessor reflects the live value of the public property.
     */
    public function test_time_warnings_accessor_reflects_public_property(): void
    {
        $missing = new TimesheetMissing();

        $this->assertNull($missing->time_warnings);

        $missing->time_warnings = ['Some warning'];

        $this->assertSame(['Some warning'], $missing->time_warnings);
    }

    /**
     * The create_entry / new_* pseudo-column mutators capture values on their public
     * properties and never leak into the underlying attributes array.
     */
    public function test_pseudo_column_mutators_populate_public_properties(): void
    {
        $missing = new TimesheetMissing();
        $missing->fill([
            'create_entry' => true,
            'new_on_duty' => '2026-01-01 10:00:00',
            'new_off_duty' => '2026-01-01 12:00:00',
            'new_position_id' => 42,
        ]);

        $this->assertTrue($missing->create_entry);
        $this->assertSame('2026-01-01 10:00:00', $missing->new_on_duty);
        $this->assertSame('2026-01-01 12:00:00', $missing->new_off_duty);
        $this->assertSame(42, $missing->new_position_id);

        $attributes = $missing->getAttributes();
        $this->assertArrayNotHasKey('create_entry', $attributes);
        $this->assertArrayNotHasKey('new_on_duty', $attributes);
        $this->assertArrayNotHasKey('new_off_duty', $attributes);
        $this->assertArrayNotHasKey('new_position_id', $attributes);
    }

    /**
     * The additional notes mutators trim values and capture them on their public
     * properties without dirtying the attributes array.
     */
    public function test_additional_notes_mutators_trim_and_capture_on_public_properties(): void
    {
        $missing = new TimesheetMissing();
        $missing->fill([
            'additional_notes' => '   user note   ',
            'additional_admin_notes' => '   admin note   ',
            'additional_wrangler_notes' => '   wrangler note   ',
        ]);

        $this->assertSame('user note', $missing->additionalNotes);
        $this->assertSame('admin note', $missing->additionalAdminNotes);
        $this->assertSame('wrangler note', $missing->additionalWranglerNotes);

        $attributes = $missing->getAttributes();
        $this->assertArrayNotHasKey('additional_notes', $attributes);
        $this->assertArrayNotHasKey('additional_admin_notes', $attributes);
        $this->assertArrayNotHasKey('additional_wrangler_notes', $attributes);
        $this->assertFalse($missing->isDirty('additional_notes'));
    }

    /**
     * The additional notes mutators convert blank or null values to null.
     */
    public function test_additional_notes_mutators_blank_to_null(): void
    {
        $missing = new TimesheetMissing();
        $missing->fill([
            'additional_notes' => '   ',
            'additional_admin_notes' => '',
            'additional_wrangler_notes' => null,
        ]);

        $this->assertNull($missing->additionalNotes);
        $this->assertNull($missing->additionalAdminNotes);
        $this->assertNull($missing->additionalWranglerNotes);
    }
}
