<?php

namespace Rushing\DataFilters\SavedFilters;

use InvalidArgumentException;

/**
 * A saved filter value whose type can't be coerced to its declared filter type.
 * Thrown by {@see FilterValueCaster}; the validator catches it per-key and turns it
 * into a 422 (ADR-0007).
 */
class InvalidFilterValue extends InvalidArgumentException
{
    public static function for(string $expected): self
    {
        return new self("must be {$expected}");
    }
}
