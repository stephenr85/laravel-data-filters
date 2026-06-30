<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Includable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\Partial;
use Rushing\DataFilters\Operators\Range;
use Rushing\DataFilters\Operators\Scope;
use Rushing\DataFilters\Operators\Search;
use Rushing\DataFilters\Operators\Set;
use Spatie\LaravelData\Data;

/**
 * A mixed Filter Data class exercising the full operator set, a sort, and an
 * include — the demo resource for the operator-breadth tracer.
 */
class GadgetFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public ?string $color = null,

        #[Filterable(Partial::class)]
        public ?string $name = null,

        #[Filterable(Range::class)]
        public ?int $weight = null,

        #[Filterable(Set::class)]
        public ?GadgetStatus $status = null,

        #[Filterable(Search::class, columns: ['name', 'color'])]
        public ?string $search = null,

        #[Filterable(Scope::class, scope: 'flagged')]
        public ?bool $flagged = null,

        #[Sortable]
        public ?string $createdAt = null,

        #[Includable]
        public mixed $parts = null,
    ) {}
}
