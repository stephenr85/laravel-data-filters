<?php

use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\ColorOptionsSource;
use Rushing\DataFilters\Tests\Stubs\RelationalFilterData;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;

it('resolves a closure options source', function () {
    DataFilter::options('colors', fn (?string $search = null) => [
        ['value' => 'red', 'label' => 'Red'],
    ]);

    expect(DataFilter::hasOptions('colors'))->toBeTrue()
        ->and(DataFilter::resolveOptions('colors'))->toBe([['value' => 'red', 'label' => 'Red']]);
});

it('resolves a class-string options source through the container with a search term', function () {
    DataFilter::options('colors', ColorOptionsSource::class);

    expect(DataFilter::resolveOptions('colors', 'blue'))->toBe([['value' => 'blue', 'label' => 'Blue']]);
});

it('emits optionsRef plus value/label keys for a relational filter', function () {
    $props = (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(RelationalFilterData::class))['properties'];

    expect($props['colorIds']['x-filter'])->toMatchArray([
        'operator' => 'set',
        'control' => 'multiselect',
        'optionsRef' => 'colors',
        'valueKey' => 'value',
        'labelKey' => 'label',
        'searchable' => true,
    ]);
});

it('ships no http route from the package', function () {
    $optionRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'option'));

    expect($optionRoutes)->toBeEmpty();
});

// --- registry-kernel: the options registry declares itself -----------------------------------------

it('declares itself in the popcorn kernel, so popcorn:registries can show where options are registered', function () {
    $declaration = Rushing\Popcorn\Registries\IsRegistry::of(Rushing\DataFilters\Options\OptionsRegistry::class);

    expect($declaration)->not->toBeNull()
        ->and($declaration->root)->toBe('data-filters.options')
        ->and($declaration->arity)->toBe([Rushing\Popcorn\Registries\RegistryArity::PickOne])
        // Supersede is what this class always did — registration was a plain array assignment, so a
        // second registration under one key replaced the first. The declaration records the behaviour
        // rather than a wish.
        ->and($declaration->onDuplicate)->toBe(Rushing\Popcorn\Registries\OnDuplicate::Supersede);
});

it('reaches the shared index at boot, because declaring and indexing are two acts', function () {
    $index = app(Rushing\Popcorn\Registries\RegistryIndex::class);

    // routeTo() is the read that matters: it answers "who owns this branch of the keyspace", which is
    // the question `popcorn:registries` and `new ExistsInRegistry('data-filters.options')` both ask.
    // Asserting on the declaration alone would pass even if the provider never described it — this is
    // the half that catches a class that declares and is never indexed.
    expect($index->routeTo('data-filters.options'))
        ->toBeInstanceOf(Rushing\DataFilters\Options\OptionsRegistry::class);

    expect($index->declarationAt('data-filters.options')?->root)->toBe('data-filters.options');

    // The control: an undescribed root routes nowhere, so the assertion above is discriminating.
    expect($index->routeTo('data-filters.nothing-owns-this'))->toBeNull();
});

it('keeps `resolve` for the kernel and gives the old meaning a name of its own', function () {
    DataFilter::options('colors', fn (?string $search = null) => [['value' => 'red', 'label' => 'Red']]);

    $registry = app(Rushing\DataFilters\Options\OptionsRegistry::class);

    // The kernel's resolve() hands back the ENTRY — here, the closure itself.
    expect($registry->resolve('colors'))->toBeInstanceOf(Closure::class)
        // optionsFor() is what resolve() used to mean: run the entry and give me the options.
        ->and($registry->optionsFor('colors'))->toBe([['value' => 'red', 'label' => 'Red']]);
});

it('still throws the same exception for an unregistered key, because callers may be catching it', function () {
    expect(fn () => app(Rushing\DataFilters\Options\OptionsRegistry::class)->optionsFor('nope'))
        ->toThrow(InvalidArgumentException::class, 'No options source registered for key [nope].');
});
