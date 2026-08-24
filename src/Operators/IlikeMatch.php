<?php

namespace Rushing\DataFilters\Operators;

use Illuminate\Database\Eloquent\Builder;
use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Case-insensitive `ILIKE` match against a column. `prefix` → `value%` (the
 * name-search the legacy builders used); `contains` → `%value%` (the keyword-on-name
 * fallback). Column defaults to the filter key; override it to point a virtual key
 * (e.g. `keywords`) at a real column (e.g. `name`).
 *
 * Extracted DOWN from the host (`Splicewire\Tower\Data\Filters\Operators\IlikeMatch`) into this
 * foundation so both the host's other filter DTOs and the beam-taxonomy cone can reach it
 * without an up-edge (tower-api-dissolution issue 17 P2). The old FQCN is kept resolving via
 * a back-compat `class_alias` in the host until every call site migrates.
 */
class IlikeMatch extends Operator
{
    public function __construct(
        public string $mode = 'contains',
        public ?string $column = null,
        ?string $options = null,
    ) {
        parent::__construct($options);
    }

    protected function operatorName(): string
    {
        return 'ilike';
    }

    public function toAllowedFilter(string $name, string $column): AllowedFilter
    {
        $column = $this->column ?? $column;
        $mode = $this->mode;

        return AllowedFilter::callback($name, function (Builder $q, $value) use ($column, $mode): void {
            $pattern = $mode === 'prefix' ? "{$value}%" : "%{$value}%";
            $q->where($column, 'ilike', $pattern);
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return [
            'control' => $this->mode === 'prefix' ? 'text' : 'search',
            ...$this->optionsControl($property),
        ];
    }
}
