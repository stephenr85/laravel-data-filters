<?php

namespace Rushing\DataFilters\Schema;

use Illuminate\Support\Str;
use ReflectionProperty;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\LaravelDataSchemas\Strategies\SchemaStrategy;
use Rushing\LaravelDataSchemas\Strategies\SchemaStrategyContext;

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
            $name = $filterable->name ?? Str::snake($property->getName());
            $schema['x-filter'] = $filterable->operator()->keyword($property, $name);
        }

        if ($sortable = $this->firstAttribute($property, Sortable::class)) {
            $name = $sortable->name ?? Str::snake($property->getName());
            $schema['x-sort'] = ['name' => $name];
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
