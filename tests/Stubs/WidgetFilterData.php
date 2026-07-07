<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Operators\Exact;
use Spatie\LaravelData\Data;

/**
 * The demo Filter Data class: its properties are the resource's queryable surface.
 */
class WidgetFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?string $color = null,
        public ?string $name = null,
    ) {}
}
