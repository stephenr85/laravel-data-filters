<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Attributes;

use Attribute;

/**
 * Declares a column-mapped allowed sort on a Filter Data class property. Emits an
 * `x-sort` keyword so the form's sort selector has parity with the query. An
 * imperative sort that does not map to a property drops to the Query class escape
 * hatch instead.
 *
 *   #[Sortable]                       // sort key = snake(property)
 *   #[Sortable(name: 'created_at')]   // sort key override
 *   #[Sortable(column: 'created_at')] // sort key = property, ORDER BY column
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Sortable
{
    public function __construct(
        public ?string $name = null,
        public ?string $column = null,
    ) {}
}
