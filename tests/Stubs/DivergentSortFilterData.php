<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Spatie\LaravelData\Data;

/**
 * The default-sort regression shape: the default-sort property's sort KEY (`heaviness`)
 * deliberately diverges from its COLUMN (`weight`, a real `gadgets` column). Everything the
 * bug needs — a string-shaped default sort has nowhere to carry the mapping, so the default
 * path used to order by a column that does not exist.
 */
class DivergentSortFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?string $color = null,

        #[Sortable(name: 'heaviness', column: 'weight', default: true, direction: 'desc')]
        public ?int $weight = null,
    ) {}
}
