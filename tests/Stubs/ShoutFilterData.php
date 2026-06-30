<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Attributes\Filterable;
use Spatie\LaravelData\Data;

class ShoutFilterData extends Data
{
    public function __construct(
        #[Filterable(ShoutExact::class)]
        public ?string $code = null,
    ) {}
}
