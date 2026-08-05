<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Sortable;
use Spatie\LaravelData\Data;

/**
 * Stub for the DTO-declared default sort: `rank` opts in as the default (descending),
 * `name` is a plain allowed sort with no default.
 */
class SortedData extends Data
{
    public function __construct(
        #[Sortable]
        public ?string $name = null,
        #[Sortable(default: true, direction: 'desc')]
        public ?int $rank = null,
    ) {}
}
