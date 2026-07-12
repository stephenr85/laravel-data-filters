<?php

use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\GadgetFilterData;
use Rushing\DataFilters\Tests\Stubs\ShoutFilterData;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;

function gadgetProps(): array
{
    return (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(GadgetFilterData::class))['properties'];
}

it('emits the correct x-filter operator per property', function () {
    $props = gadgetProps();

    expect($props['color']['x-filter']['operator'])->toBe('exact')
        ->and($props['name']['x-filter']['operator'])->toBe('partial')
        ->and($props['weight']['x-filter']['operator'])->toBe('range')
        ->and($props['status']['x-filter']['operator'])->toBe('set')
        ->and($props['search']['x-filter']['operator'])->toBe('search')
        ->and($props['flagged']['x-filter']['operator'])->toBe('scope');
});

it('refines the control by the property type', function () {
    $props = gadgetProps();

    expect($props['weight']['x-filter']['control'])->toBe('number-range')
        ->and($props['name']['x-filter']['control'])->toBe('text')
        ->and($props['search']['x-filter']['control'])->toBe('search');
});

it('inlines finite-domain options for a backed-enum set filter', function () {
    $status = gadgetProps()['status']['x-filter'];

    expect($status['control'])->toBe('multiselect')
        ->and(collect($status['options'])->pluck('value')->all())->toBe(['active', 'archived', 'draft']);
});

it('emits an x-sort keyword for a sortable property', function () {
    expect(gadgetProps()['createdAt']['x-sort'])->toBe(['name' => 'created_at']);
});

it('yields the complete keyword set for a mixed filter data class', function () {
    $props = gadgetProps();

    $filterable = collect($props)->filter(fn ($p) => isset($p['x-filter']))->keys()->all();

    expect($filterable)->toBe(['color', 'name', 'weight', 'status', 'search', 'flagged']);
});

it('emits a host operators x-filter keyword from the schema strategy', function () {
    $props = (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(ShoutFilterData::class))['properties'];

    expect($props['code']['x-filter']['operator'])->toBe('shout');
});
