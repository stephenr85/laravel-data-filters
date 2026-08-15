<?php

namespace Rushing\DataFilters\Tests\Stubs\DiscoveryConflicts;

use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;
use Spatie\LaravelData\Data;

/**
 * A second class claiming the `gizmos` key with a DIFFERENT Query class — the collision
 * case. Kept out of `Stubs/Discovery/` on purpose so a path scan of that directory stays
 * deterministic; the collision tests drive discovery through the explicit class list,
 * where declaration order is the test's to control.
 */
#[ResourceFilter(key: 'gizmos', query: WidgetQuery::class)]
class ConflictingGizmoFilterData extends Data
{
    public function __construct(
        public ?string $color = null,
    ) {}
}
