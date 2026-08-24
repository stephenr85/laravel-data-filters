<?php

namespace Rushing\DataFilters;

use Illuminate\Support\Str;
use ReflectionProperty;

/**
 * The one place a facet's wire name is decided.
 *
 * A `#[Filterable]` / `#[Sortable]` / `#[Includable]` property publishes a name on the query
 * string, and that name has to be identical in the two artifacts derived from the same
 * declaration: the JSON-Schema `x-filter`/`x-sort` keyword the reference and the UI read
 * ({@see \Rushing\DataFilters\Schema\FilterableAttributesStrategy}), and the spatie
 * allowed-set the runtime gate is built from ({@see \Rushing\DataFilters\Reflection\FilterReflector}).
 * Both used to inline the same `Str::snake($property->getName())` expression — eight copies of
 * one convention, which meant flipping it was eight edits with a drift window in between, and a
 * half-done flip would have documented one spelling while accepting another.
 *
 * The convention is **camelCase** (api-surface-coherence 22, decided in ticket 07 §4): the wire
 * matches the property, `$externalRef` → `filter[externalRef]`, and the public surface stops
 * reading `filter[externalRef]` beside `filter[produced_by_circuit]`. An explicit `name:` override
 * always wins and is passed through verbatim — a compound key like `tags:all` is not a spelling of
 * anything and is nobody's to re-case.
 */
class FacetName
{
    /**
     * The wire name for a declared facet: the attribute's explicit override when it has one,
     * else the camelCase of the property name.
     */
    public static function for(ReflectionProperty $property, ?string $override = null): string
    {
        return $override ?? Str::camel($property->getName());
    }

    /**
     * The COLUMN a declared filter narrows, which is a different thing from its wire name and
     * always was — they were merely the same string until the wire went camelCase.
     *
     * Every operator built its `AllowedFilter` from the wire name alone, so `filter[external_ref]`
     * happened to produce `WHERE external_ref = ?` because the snake key and the snake column
     * coincided. Flipping the wire to `externalRef` produced `WHERE "externalRef" = ?` — a column
     * that does not exist — which is how a coincidence that had held estate-wide finally announced
     * itself. The column keeps the snake derivation it always had; only the wire moved.
     *
     * An operator with its own declared `column:` (a `NullableExact`, an `IlikeMatch`) still wins:
     * this is the default it falls back to, not an override of it.
     */
    public static function column(ReflectionProperty $property): string
    {
        return Str::snake($property->getName());
    }
}
