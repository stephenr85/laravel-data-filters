<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

use ReflectionProperty;
use Rushing\DataFilters\Contracts\FilterOperator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * A host-defined operator implementing the contract directly (not extending the
 * package base) — proving open-core: a host adds an operator without editing any
 * package internals or a central enum.
 */
final class ShoutExact implements FilterOperator
{
    public function toAllowedFilter(string $name): AllowedFilter
    {
        return AllowedFilter::callback($name, function ($query, $value) use ($name): void {
            $query->where($name, strtoupper((string) $value));
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => 'text'];
    }

    public function keyword(ReflectionProperty $property, string $name): array
    {
        return ['operator' => 'shout', 'name' => $name, 'control' => 'text'];
    }
}
