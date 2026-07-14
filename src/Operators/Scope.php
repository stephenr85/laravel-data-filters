<?php

namespace Rushing\DataFilters\Operators;

use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Escape hatch binding a filter key to a custom Eloquent query scope — for
 * predicates that aren't a plain column comparison. `#[Filterable(Scope::class,
 * scope: 'producedByCircuit')]` maps `filter[produced_by_circuit]=…` onto the
 * model's `scopeProducedByCircuit`. The control defaults to text; a host can
 * refine it explicitly (`control: 'search'`) or, when the scope takes a
 * relational id, hang an Options Source off it (`options: 'circuits'`) — which
 * turns the plain text box into a type-ahead resolving `{value,label}` rows
 * through the host endpoint, exactly like a relational `Set` (ADR-0006). An
 * options-backed scope defaults its control to `select` (single relational id).
 */
class Scope extends Operator
{
    public function __construct(
        public string $scope,
        public ?string $control = null,
        ?string $options = null,
    ) {
        parent::__construct($options);
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
        // An options-backed scope is a single relational id: default to `select`
        // and reference the Options Source, unless the host pinned a control.
        if ($this->options !== null) {
            return [
                'control' => $this->control ?? 'select',
                ...$this->optionsControl($property),
            ];
        }

        return ['control' => $this->control ?? 'text'];
    }
}
