<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Operators;

use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Exact-match filter: `filter[status]=draft` → `WHERE status = 'draft'`. A finite
 * domain (backed enum / bool) renders as a select with inlined options; a
 * relational column references an Options Source; anything else is a plain text
 * control.
 */
final class Exact extends Operator
{
    protected function operatorName(): string
    {
        return 'exact';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        return AllowedFilter::exact($name);
    }

    public function toControl(ReflectionProperty $property): array
    {
        $options = $this->optionsControl($property);

        return [
            'control' => $options === [] ? 'text' : 'select',
            ...$options,
        ];
    }
}
