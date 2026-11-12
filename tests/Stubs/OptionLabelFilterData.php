<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\Set;
use Spatie\LaravelData\Data;

/** localization 14 stub: a boolean filter, an enum filter without labels and one with them. */
class OptionLabelFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?bool $active = null,

        #[Filterable(Set::class)]
        public ?GadgetStatus $status = null,

        #[Filterable(Set::class)]
        public ?TierLevel $tier = null,
    ) {}
}
