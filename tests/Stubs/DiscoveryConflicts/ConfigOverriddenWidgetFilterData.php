<?php

namespace Rushing\DataFilters\Tests\Stubs\DiscoveryConflicts;

use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\GadgetQuery;
use Spatie\LaravelData\Data;

/**
 * Claims `widget` — a key the test harness already seeds from
 * `config('data-filters.resources')` — with entirely different wiring. Discovery must lose.
 */
#[ResourceFilter(key: 'widget', model: Gadget::class, query: GadgetQuery::class)]
class ConfigOverriddenWidgetFilterData extends Data
{
    public function __construct(
        public ?string $color = null,
    ) {}
}
