<?php

namespace Rushing\DataFilters\Tests\Stubs\Discovery;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Operators\Exact;
use Spatie\LaravelData\Data;

/**
 * The declared-but-incomplete shape: a package names the resource, but its concrete Query
 * class lives in a host app it can't see, so `query` stays null and discovery registers
 * nothing — the host completes the wiring imperatively.
 */
#[ResourceFilter(key: 'unwired')]
class UnwiredFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?string $color = null,
    ) {}
}
