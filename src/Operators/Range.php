<?php

namespace Rushing\DataFilters\Operators;

use DateTimeInterface;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Bounded range over one column. The value is a `{min, max}` (or `[min, max]`)
 * pair; either bound may be omitted. `filter[created_at][min]=2026-01-01` →
 * `WHERE created_at >= '2026-01-01'`. The control refines by the property's type:
 * a date type → `date-range`, a numeric type → `number-range`.
 */
class Range extends Operator
{
    protected function operatorName(): string
    {
        return 'range';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        return AllowedFilter::callback($name, function ($query, $value) use ($name): void {
            $min = is_array($value) ? ($value['min'] ?? $value[0] ?? null) : null;
            $max = is_array($value) ? ($value['max'] ?? $value[1] ?? null) : null;

            if ($min !== null && $min !== '') {
                $query->where($name, '>=', $min);
            }
            if ($max !== null && $max !== '') {
                $query->where($name, '<=', $max);
            }
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => $this->isDate($property) ? 'date-range' : 'number-range'];
    }

    private function isDate(ReflectionProperty $property): bool
    {
        $type = $property->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }

        $name = $type->getName();

        return is_a($name, DateTimeInterface::class, true);
    }
}
