<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Operators\Set;
use Spatie\LaravelData\Data;

class RelationalFilterData extends Data
{
    public function __construct(
        #[Filterable(Set::class, options: 'colors')]
        public ?array $colorIds = null,
    ) {}
}
