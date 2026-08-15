<?php

namespace Rushing\DataFilters\Tests\Stubs\Discovery;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Tests\Stubs\GadgetQuery;
use Spatie\LaravelData\Data;

/**
 * The alias shape: a canonical key plus a legacy singular one, declared as two
 * `#[ResourceFilter]` instances sharing a `resource` — the repeatable attribute standing in
 * for the old static `$aliases` array. Neither declares a `model`, so both lean on the
 * host's `ResourceModelResolver` and resolve it under the canonical key.
 */
#[ResourceFilter(key: 'gizmos', query: GadgetQuery::class)]
#[ResourceFilter(key: 'gizmo', resource: 'gizmos', query: GadgetQuery::class)]
class AliasedGizmoFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?string $color = null,
    ) {}
}
