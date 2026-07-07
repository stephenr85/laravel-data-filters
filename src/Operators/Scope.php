<?php

namespace Rushing\DataFilters\Operators;

use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Escape hatch binding a filter key to a custom Eloquent query scope — for
 * predicates that aren't a plain column comparison. `#[Filterable(Scope::class,
 * scope: 'producedByCircuit')]` maps `filter[produced_by_circuit]=…` onto the
 * model's `scopeProducedByCircuit`. The control defaults to text; a host can
 * refine it by subclassing.
 */
class Scope extends Operator
{
    public function __construct(
        public string $scope,
        public string $control = 'text',
    ) {
        parent::__construct();
    }

    protected function operatorName(): string
    {
        return 'scope';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        return AllowedFilter::scope($name, $this->scope);
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => $this->control];
    }
}
