<?php

namespace App\Lib\Reports;

/**
 * An immutable definition of a single coverage column ("post") in a shift coverage report.
 *
 * Replaces the legacy positional tuple [positionId, shortTitle, countOnly, parenthetical]
 * with named, self-documenting fields.
 */
readonly class CoveragePost
{
    /**
     * @param int|array<int, int> $positionId a single position id or a list of position ids covered by this column
     * @param string $shortTitle the column header label
     * @param bool $countOnly when true the column reports a headcount rather than a list of people
     * @param array<int, string> $parenthetical map of position id => parenthetical label appended after a callsign
     */
    public function __construct(
        public int|array $positionId,
        public string $shortTitle,
        public bool $countOnly = false,
        public array $parenthetical = [],
    ) {
    }

    /**
     * Build a CoveragePost from the legacy positional-tuple definition.
     *
     * @param array{0: int|array<int, int>, 1: string, 2?: bool, 3?: array<int, string>} $tuple
     */
    public static function fromTuple(array $tuple): self
    {
        return new self(
            positionId: $tuple[0],
            shortTitle: $tuple[1],
            countOnly: $tuple[2] ?? false,
            parenthetical: $tuple[3] ?? [],
        );
    }
}
