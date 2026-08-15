<?php

namespace Rushing\DataFilters\Attributes;

use Attribute;
use Rushing\DataFilters\Reflection\FilterReflector;

/**
 * Declares a column-mapped allowed sort on a Filter Data class property. Emits an
 * `x-sort` keyword so the form's sort selector has parity with the query. An
 * imperative sort that does not map to a property drops to the Query class escape
 * hatch instead.
 *
 *   #[Sortable]                        // sort key = snake(property)
 *   #[Sortable(name: 'created_at')]    // sort key override
 *   #[Sortable(column: 'created_at')]  // sort key = property, ORDER BY column
 *   #[Sortable(default: true)]         // the resource's default sort (asc)
 *   #[Sortable(default: true, direction: 'desc')]  // default, descending
 *
 * At most one property should carry `default: true`; the reflector returns the
 * first it finds. It supersedes a hand-written `ResourceQuery::defaultSort()` only
 * when that returns null — an override still wins.
 *
 * The default sort is read as a built `AllowedSort` ({@see FilterReflector::defaultAllowedSort()}),
 * or as a raw column/direction pair for a plain-Eloquent consumer
 * ({@see FilterReflector::defaultSortColumn()}) — never as a sign-prefixed string,
 * which has nowhere to carry a `name`/`column` divergence and so silently ordered
 * by the sort key.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Sortable
{
    public function __construct(
        public ?string $name = null,
        public ?string $column = null,
        public bool $default = false,
        public string $direction = 'asc',
    ) {}
}
