<?php

namespace Rushing\DataFilters\Contracts;

use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * One operator that maps a single filter three ways at once (ADR-0005):
 *
 *  - {@see toAllowedFilter()} — the server-side spatie/laravel-query-builder query. Takes the
 *    wire NAME and the COLUMN separately: they were the same string until the wire went
 *    camelCase ({@see \Rushing\DataFilters\FacetName::column()}), and an operator that
 *    narrows a column of its own naming simply ignores the second argument.
 *  - {@see toControl()}       — the UI form control, refined by the property's type,
 *  - {@see keyword()}         — the `x-filter` JSON-Schema vendor keyword.
 *
 * Open-core: the package ships `Exact`, `Partial`, `Range`, `Set`, `Search`, and
 * `Scope`; a host adds its own by implementing this contract, with no central enum
 * to edit. Operator-specific configuration (a relational `options` reference, a
 * bound scope name) is passed to the operator's constructor by the `#[Filterable]`
 * attribute.
 */
interface FilterOperator
{
    public function toAllowedFilter(string $name, string $column): AllowedFilter;

    /**
     * @return array<string, mixed>
     */
    public function toControl(ReflectionProperty $property): array;

    /**
     * @return array<string, mixed>
     */
    public function keyword(ReflectionProperty $property, string $name): array;
}
