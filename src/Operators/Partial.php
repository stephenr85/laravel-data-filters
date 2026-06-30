<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Operators;

use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Substring match: `filter[name]=ace` → `WHERE name LIKE '%ace%'`. Always a text
 * control.
 */
final class Partial extends Operator
{
    protected function operatorName(): string
    {
        return 'partial';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        return AllowedFilter::partial($name);
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => 'text'];
    }
}
