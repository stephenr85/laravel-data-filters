<?php

namespace Rushing\DataFilters\Operators;

use Illuminate\Database\Eloquent\Builder;
use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Exact match where an empty value means IS NULL — preserves the Silo `parentId`
 * behaviour (empty `filter[parentId]` selects root silos). Column defaults to the
 * filter key; pass `column` to target a renamed column (e.g. `parent_id`).
 *
 * Extracted DOWN from the host (`Splicewire\Tower\Data\Filters\Operators\NullableExact`) into this
 * foundation with the beam-taxonomy Silo cone (tower-api-dissolution issue 17 P2). The old FQCN
 * is kept resolving via a back-compat subclass shim in the host.
 */
class NullableExact extends Operator
{
    public function __construct(
        public ?string $column = null,
        ?string $options = null,
    ) {
        parent::__construct($options);
    }

    protected function operatorName(): string
    {
        return 'exact';
    }

    public function toAllowedFilter(string $name, string $column): AllowedFilter
    {
        $column = $this->column ?? $column;

        return AllowedFilter::callback($name, function (Builder $q, $value) use ($column): void {
            if ($value === null || $value === '' || $value === []) {
                $q->whereNull($column);

                return;
            }

            $q->where($column, $value);
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => 'select', ...$this->optionsControl($property)];
    }
}
