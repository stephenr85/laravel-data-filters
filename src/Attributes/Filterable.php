<?php

namespace Rushing\DataFilters\Attributes;

use Attribute;
use Rushing\DataFilters\Contracts\FilterOperator;

/**
 * Binds a Filter Operator to a Filter Data class property — the one attribute name
 * to grep for (ADR-0005). The operator is referenced by class-string; any
 * operator-specific arguments (a relational `options` key, a bound `scope` name)
 * are passed inline as named arguments and forwarded to the operator's
 * constructor.
 *
 *   #[Filterable(Exact::class)]
 *   #[Filterable(Set::class, options: 'silos')]
 *   #[Filterable(Scope::class, scope: 'producedByCircuit')]
 *
 * `$name` overrides the filter key (defaulting to the snake_case property name) so
 * the query key can target the underlying column rather than the API-renamed
 * output field (ADR-0002).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Filterable
{
    /**
     * @param  class-string<FilterOperator>  $operator
     * @param  array<string, mixed>  $arguments  operator-specific constructor args
     */
    public array $arguments;

    public function __construct(
        public string $operator,
        public ?string $name = null,
        mixed ...$arguments,
    ) {
        $this->arguments = $arguments;
    }

    public function operator(): FilterOperator
    {
        return new ($this->operator)(...$this->arguments);
    }
}
