<?php

use Rushing\DataFilters\Reflection\FilterReflector;
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
