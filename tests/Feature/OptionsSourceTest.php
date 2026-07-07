<?php

use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\ColorOptionsSource;
use Rushing\DataFilters\Tests\Stubs\RelationalFilterData;
use Rushing\LaravelDataSchemas\Generators\JsonSchemaGenerator;

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
