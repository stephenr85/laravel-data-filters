<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Attributes;

use Attribute;

/**
 * Declares an allowed relationship include on a Filter Data class property. Query-
 * facing only — no form control is emitted (an include is not a filter the user
 * sets). An imperative include that does not map to a property drops to the Query
 * class escape hatch.
 *
 *   #[Includable]                  // include name = snake(property)
 *   #[Includable(name: 'lineage')] // include name override
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Includable
{
    public function __construct(
        public ?string $name = null,
    ) {}
}
