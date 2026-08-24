<?php

use Rushing\DataFilters\FacetName;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Keywords;
use Rushing\DataFilters\Reflection\FilterReflector;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\GadgetFilterData;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;

/**
 * The wire name is one convention with two derived artifacts — the `x-filter`/`x-sort` keyword
 * the reference and the UI read, and the spatie allowed-set the runtime gate is built from.
 * These used to be two inlined copies of `Str::snake(...)`; {@see FacetName} is now the single
 * source, and the test that matters is that the two artifacts still agree, not that either one
 * spells any particular name.
 */
it('defaults a facet name to the camelCase of its property', function () {
    $property = new ReflectionProperty(GadgetFilterData::class, 'ownedBy');

    expect(FacetName::for($property))->toBe('ownedBy');
});

it('passes an explicit override through verbatim, whatever its casing', function () {
    $property = new ReflectionProperty(GadgetFilterData::class, 'ownedBy');

    // A compound key is not a spelling of anything — it is nobody's to re-case.
    expect(FacetName::for($property, 'tags:all'))->toBe('tags:all')
        ->and(FacetName::for($property, 'external_ref'))->toBe('external_ref');
});

it('names a facet identically in the schema keyword and the runtime allowed-set', function () {
    $properties = (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(GadgetFilterData::class))['properties'];

    $documented = collect($properties)
        ->filter(fn (array $schema) => isset($schema[Keywords::Filter]))
        ->map(fn (array $schema) => $schema[Keywords::Filter]['name'])
        ->values()
        ->sort()
        ->values()
        ->all();

    $accepted = collect((new FilterReflector)->allowedFilters(GadgetFilterData::class))
        ->map(fn ($filter) => $filter->getName())
        ->sort()
        ->values()
        ->all();

    expect($documented)->not->toBeEmpty()
        ->and($documented)->toBe($accepted);
});

/**
 * The regression this whole seam exists for. Before the camelCase flip, every operator built its
 * `AllowedFilter` from the wire name alone and it worked — because the snake wire key and the snake
 * column were the same string. `filter[externalRef]` turned that coincidence into
 * `where "externalRef" = ?`, a column no table has, on every `Exact`/`Partial`/`Set`/`Range` facet
 * in the estate. The wire name and the column are now derived separately and must stay that way.
 */
it('narrows the snake column while publishing the camelCase wire name', function () {
    $property = new ReflectionProperty(GadgetFilterData::class, 'ownedBy');

    expect(FacetName::for($property))->toBe('ownedBy')
        ->and(FacetName::column($property))->toBe('owned_by');
});

it('builds an AllowedFilter whose public name and internal column differ', function () {
    $filter = (new Exact)->toAllowedFilter('externalRef', 'external_ref');

    expect($filter->getName())->toBe('externalRef')
        ->and($filter->getInternalName())->toBe('external_ref');
});
