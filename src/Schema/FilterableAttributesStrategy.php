<?php

namespace Rushing\DataFilters\Schema;

use ReflectionProperty;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\FacetName;
use Rushing\DataFilters\Keywords;
use Schemastud\DataSchemas\Strategies\SchemaStrategy;
use Schemastud\DataSchemas\Strategies\SchemaStrategyContext;

/**
 * Projects `#[Filterable]` and `#[Sortable]` on a Filter Data class property to the
 * `x-filter` / `x-sort` JSON-Schema vendor keywords (ADR-0001/0003) — the same
 * declaration the query is built from, so the UI filter form and the server query
 * can never drift. Self-registered into `config('data-schemas.strategies')` by the
 * package service provider; contributes nothing to a property without these
 * attributes. Like every `x-*` keyword these are stripped by `forLlmStrict`.
 */
class FilterableAttributesStrategy implements SchemaStrategy
{
    public function apply(ReflectionProperty $property, array $schema, SchemaStrategyContext $context): array
    {
        if ($filterable = $this->firstAttribute($property, Filterable::class)) {
            $name = FacetName::for($property, $filterable->name);
            $schema[Keywords::Filter] = $filterable->operator()->keyword($property, $name);
        }

        if ($sortable = $this->firstAttribute($property, Sortable::class)) {
            $schema[Keywords::Sort] = ['name' => FacetName::for($property, $sortable->name)];
        }

        return $schema;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    private function firstAttribute(ReflectionProperty $property, string $attribute): ?object
    {
        $attrs = $property->getAttributes($attribute);

        return empty($attrs) ? null : $attrs[0]->newInstance();
    }
}
