<?php

namespace Tests\Feature;

use App\Models\PositionLineup;
use Tests\TestCase;

class PositionLineupAttributeTest extends TestCase
{
    /**
     * The position_ids getter returns the value held on the public property.
     */
    public function test_position_ids_getter_reads_public_property(): void
    {
        $lineup = new PositionLineup();
        $lineup->position_ids = [10, 20, 30];

        $this->assertSame([10, 20, 30], $lineup->position_ids);
    }

    /**
     * Mass-assigning position_ids diverts the value onto the public property and
     * does not leak into the attribute bag, which would break save() because the
     * position_lineup table has no position_ids column.
     */
    public function test_position_ids_setter_diverts_to_property_without_polluting_attributes(): void
    {
        $lineup = new PositionLineup();
        $lineup->fill(['position_ids' => [1, 2, 3]]);

        $this->assertSame([1, 2, 3], $lineup->position_ids);
        $this->assertArrayNotHasKey('position_ids', $lineup->getAttributes());
    }

    /**
     * The appended position_ids attribute is included when the model is
     * serialized to an array.
     */
    public function test_position_ids_is_serialized_when_appended(): void
    {
        $lineup = new PositionLineup();
        $lineup->position_ids = [5, 6];
        $lineup->append('position_ids');

        $array = $lineup->toArray();

        $this->assertArrayHasKey('position_ids', $array);
        $this->assertSame([5, 6], $array['position_ids']);
    }

    /**
     * Serialization reflects the live property value on each call because object
     * caching is disabled on the accessor.
     */
    public function test_position_ids_serialization_is_not_object_cached(): void
    {
        $lineup = new PositionLineup();
        $lineup->position_ids = [5, 6];
        $lineup->append('position_ids');

        $this->assertSame([5, 6], $lineup->toArray()['position_ids']);

        $lineup->position_ids = [7, 8, 9];

        $this->assertSame([7, 8, 9], $lineup->toArray()['position_ids']);
    }
}
