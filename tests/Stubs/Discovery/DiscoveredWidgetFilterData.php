<?php

namespace Rushing\DataFilters\Tests\Stubs\Discovery;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;
use Spatie\LaravelData\Data;

/**
 * The fully self-describing shape: key, Query class, and an explicit model, so the
 * resource needs no `config('data-filters.resources')` entry and no host resolver.
 */
#[ResourceFilter(key: 'discovered_widget', model: Widget::class, query: WidgetQuery::class)]
class DiscoveredWidgetFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?string $color = null,
    ) {}
}
