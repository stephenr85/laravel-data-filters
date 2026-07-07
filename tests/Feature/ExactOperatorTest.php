<?php

use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;
use Spatie\QueryBuilder\AllowedFilter;

function colorProperty(): ReflectionProperty
{
    return new ReflectionProperty(WidgetFilterData::class, 'color');
}

it('maps to a spatie exact AllowedFilter', function () {
    $filter = (new Exact)->toAllowedFilter('color');

    expect($filter)->toBeInstanceOf(AllowedFilter::class)
        ->and($filter->getName())->toBe('color');
});

it('maps to a text form control for a plain string property', function () {
    $control = (new Exact)->toControl(colorProperty());

    expect($control)->toBe(['control' => 'text']);
});

it('maps to an x-filter keyword carrying the operator name and filter key', function () {
    $keyword = (new Exact)->keyword(colorProperty(), 'color');

    expect($keyword)->toBe([
        'operator' => 'exact',
        'name' => 'color',
        'control' => 'text',
    ]);
});
