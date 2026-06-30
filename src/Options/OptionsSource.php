<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Options;

/**
 * A host-registered provider of the selectable values for a *relational* filter
 * (ADR-0006) — e.g. `silos`, `tags`, `organizations`. Referenced from
 * `#[Filterable(options: 'silos')]` and surfaced in `x-filter` as `optionsRef`.
 * The package ships this seam and the keyword only; it owns no HTTP route, so the
 * host decides how the values reach the frontend.
 *
 * Implementations return a list of `['value' => …, 'label' => …]` rows, optionally
 * narrowed by a typeahead `$search` term.
 */
interface OptionsSource
{
    /**
     * @return list<array{value: mixed, label: string}>
     */
    public function options(?string $search = null): array;
}
