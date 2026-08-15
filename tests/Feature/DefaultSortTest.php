<?php

use Illuminate\Http\Request;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Reflection\FilterReflector;
use Rushing\DataFilters\Tests\Stubs\DivergentSortFilterData;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\SortedData;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;

/**
 * A `#[Sortable(default: true)]` on a DTO property supplies the resource's default sort, so a
 * plug-and-play resource needn't hand-write `ResourceQuery::defaultSort()` — annotate the DTO.
 */
it('reads the DTO-declared default sort, sign-prefixed for desc', function () {
    expect(app(FilterReflector::class)->defaultSort(SortedData::class))->toBe('-rank');
});

it('returns null when no property opts into a default sort', function () {
    expect(app(FilterReflector::class)->defaultSort(WidgetFilterData::class))->toBeNull();
});

/**
 * The regression: a string-shaped default sort structurally cannot carry a `name → column`
 * mapping, so whenever the two diverge the no-`?sort=` path ordered by the sort KEY — a
 * column that does not exist.
 */
it('builds a default AllowedSort carrying the declared column, not the sort key', function () {
    $sort = app(FilterReflector::class)->defaultAllowedSort(DivergentSortFilterData::class);

    expect($sort->getName())->toBe('heaviness')
        ->and($sort->getInternalName())->toBe('weight');
});

it('reports the declared column and direction for a plain-Eloquent consumer', function () {
    expect(app(FilterReflector::class)->defaultSortColumn(DivergentSortFilterData::class))
        ->toBe(['column' => 'weight', 'direction' => 'desc']);
});

it('falls back to the sort key as the column when none is declared', function () {
    expect(app(FilterReflector::class)->defaultSortColumn(SortedData::class))
        ->toBe(['column' => 'rank', 'direction' => 'desc']);
});

it('returns null from both new methods when no property opts into a default sort', function () {
    $reflector = app(FilterReflector::class);

    expect($reflector->defaultAllowedSort(WidgetFilterData::class))->toBeNull()
        ->and($reflector->defaultSortColumn(WidgetFilterData::class))->toBeNull();
});

it('orders a default-sorted list by the declared column when name and column diverge', function () {
    Gadget::create(['name' => 'light', 'color' => 'red', 'weight' => 1]);
    Gadget::create(['name' => 'heavy', 'color' => 'red', 'weight' => 9]);
    Gadget::create(['name' => 'middling', 'color' => 'red', 'weight' => 5]);

    // No `?sort=` — the default-sort path, the one that used to drop the column mapping.
    $rows = DataFilter::query('divergent')
        ->apply(Request::create('/', 'GET'))
        ->pluck('name')
        ->all();

    expect($rows)->toBe(['heavy', 'middling', 'light']);
});

it('still honours an explicit ?sort= against the same diverging declaration', function () {
    Gadget::create(['name' => 'light', 'color' => 'red', 'weight' => 1]);
    Gadget::create(['name' => 'heavy', 'color' => 'red', 'weight' => 9]);

    $rows = DataFilter::query('divergent')
        ->apply(Request::create('/', 'GET', ['sort' => 'heaviness']))
        ->pluck('name')
        ->all();

    expect($rows)->toBe(['light', 'heavy']);
});
