<?php

use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\GadgetFilterData;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;

/**
 * Pins the emitted `x-filter` / `x-sort` shape to the canonical client-side types
 * co-located at `resources/types/filter-schema.ts`. The mirrors below are that
 * file's vocabularies; if an operator starts emitting a control the TS doesn't
 * model, this fails until the union AND this mirror are updated together — the
 * anti-drift latch that lets a frontend (`@schemastud/facets`) consume the types
 * without hand-authoring, and re-drifting, its own copy.
 *
 * @see resources/types/filter-schema.ts
 */

// Mirror of `FilterControl` in resources/types/filter-schema.ts.
const CANONICAL_CONTROLS = ['text', 'select', 'multiselect', 'search', 'date-range', 'number-range'];

// Mirror of `ShippedFilterOperator` in resources/types/filter-schema.ts.
const CANONICAL_OPERATORS = ['exact', 'partial', 'range', 'set', 'search', 'scope'];

/** Every `x-filter` keyword the strategy emits for the full-operator Gadget stub. */
function gadgetFilterKeywords(): array
{
    $props = (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(GadgetFilterData::class))['properties'];

    return collect($props)
        ->map(fn ($p) => $p['x-filter'] ?? null)
        ->filter()
        ->values()
        ->all();
}

it('emits only controls the canonical TS FilterControl union models', function () {
    $emitted = collect(gadgetFilterKeywords())->pluck('control')->unique()->values()->all();

    // Gadget exercises the full shipped operator set, so this should surface every
    // control the package can produce.
    expect($emitted)->not->toBeEmpty();

    foreach ($emitted as $control) {
        expect(CANONICAL_CONTROLS)->toContain($control);
    }
});

it('exercises the range controls the app formerly drifted on', function () {
    // The app's hand-written types were missing date-range / number-range entirely;
    // assert the Range operator still produces number-range so the canonical union
    // that now models it stays earned.
    $controls = collect(gadgetFilterKeywords())->pluck('control')->all();

    expect($controls)->toContain('number-range');
    expect(CANONICAL_CONTROLS)->toContain('date-range');
});

it('emits shipped operator names within the canonical ShippedFilterOperator set', function () {
    $operators = collect(gadgetFilterKeywords())->pluck('operator')->unique()->values()->all();

    foreach ($operators as $operator) {
        expect(CANONICAL_OPERATORS)->toContain($operator);
    }

    // And every shipped name is actually reachable from the stub — no dead canon.
    expect($operators)->toEqualCanonicalizing(CANONICAL_OPERATORS);
});

it('models inline finite-domain options that the former app types omitted', function () {
    $status = collect(gadgetFilterKeywords())->firstWhere('operator', 'set');

    // `options` (inline enum/bool) is a first-class canonical field — the app's old
    // types only knew the relational `optionsRef` path.
    expect($status)->toHaveKey('options');
    expect($status['options'][0])->toHaveKeys(['value', 'label']);
});
