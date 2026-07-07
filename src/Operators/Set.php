<?php

namespace Rushing\DataFilters\Operators;

use Illuminate\Support\Arr;
use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * One-of membership: `filter[status]=draft,published` → `WHERE status IN (...)`.
 * Renders as a multi-select; a finite domain (backed enum / bool) inlines its
 * options, a relational column references an Options Source.
 */
class Set extends Operator
{
    protected function operatorName(): string
    {
        return 'set';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        return AllowedFilter::callback($name, function ($query, $value) use ($name): void {
            $query->whereIn($name, Arr::wrap($value));
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return [
            'control' => 'multiselect',
            ...$this->optionsControl($property),
        ];
    }
}
