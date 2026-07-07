<?php

namespace Rushing\DataFilters;

/**
 * The JSON-Schema extension keywords THIS package owns.
 *
 * Ownership doctrine (the JSON-LD `@context` model): the base leaf
 * (`rushing/laravel-json-reference`) owns the small cross-engine set (`@id`,
 * `x-dereference`); every other package owns and guards its OWN keywords locally.
 * There is no central keyword list to curate — a keyword is legitimate because some
 * package declares it here, and drift is caught by each package asserting what it
 * uses/emits stays within `base ∪ own` (see the KeywordOwnership test).
 *
 * `x-filter` / `x-sort` are unprefixed: filtering is a cross-cutting UI/query-parity
 * concern, not engine-private (engine-private keywords take `x-{prefix}-*`). They are
 * emitted by {@see Schema\FilterableAttributesStrategy}, so this is their single home.
 */
class Keywords
{
    public const Filter = 'x-filter';

    public const Sort = 'x-sort';

    /**
     * Every `x-` keyword this package owns / emits.
     *
     * @return list<string>
     */
    public static function owned(): array
    {
        return [self::Filter, self::Sort];
    }
}
