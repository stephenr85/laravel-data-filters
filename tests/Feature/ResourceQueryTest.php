<?php

use Illuminate\Http\Request;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\Tests\Stubs\Widget;

beforeEach(function () {
    Widget::create(['name' => 'Alpha', 'color' => 'red']);
    Widget::create(['name' => 'Beta', 'color' => 'blue']);
    Widget::create(['name' => 'Gamma', 'color' => 'red']);
});

it('resolves a resource key to its built Query class', function () {
    expect(DataFilter::query('widget'))->toBeInstanceOf(ResourceQuery::class);
});

it('filters a model by exact match through the registry', function () {
    $request = Request::create('/widgets', 'GET', ['filter' => ['color' => 'red']]);

    $results = DataFilter::query('widget')->apply($request)->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('name')->sort()->values()->all())->toBe(['Alpha', 'Gamma']);
});

it('returns every row when no filter is applied', function () {
    $request = Request::create('/widgets', 'GET');

    expect(DataFilter::query('widget')->apply($request)->get())->toHaveCount(3);
});

it('applies declared filters to a caller-provided base query', function () {
    // A pre-constraint on the base is preserved; the declared filter is layered on.
    $base = Widget::query()->where('name', '!=', 'Beta');
    $request = Request::create('/', 'GET', ['filter' => ['color' => 'red']]);

    $names = DataFilter::query('widget')->applyFiltersTo($base, $request)
        ->get()->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Alpha', 'Gamma']);
});
