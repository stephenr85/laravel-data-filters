<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Reflection\FilterReflector;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\GadgetFilterData;
use Rushing\DataFilters\Tests\Stubs\ShoutFilterData;

function gadgetResults(array $query): Collection
{
    $request = Request::create('/gadgets', 'GET', $query);

    return DataFilter::query('gadget')->apply($request)->get();
}

beforeEach(function () {
    Gadget::create(['name' => 'Alpha', 'color' => 'red', 'weight' => 5, 'status' => 'active', 'flagged' => true]);
    Gadget::create(['name' => 'Beta', 'color' => 'blue', 'weight' => 15, 'status' => 'archived', 'flagged' => false]);
    Gadget::create(['name' => 'Gamma', 'color' => 'red', 'weight' => 25, 'status' => 'draft', 'flagged' => false]);
});

it('filters by partial match', function () {
    expect(gadgetResults(['filter' => ['name' => 'lph']])->pluck('name')->all())->toBe(['Alpha']);
});

it('filters by a bounded range', function () {
    $names = gadgetResults(['filter' => ['weight' => ['min' => 10, 'max' => 20]]])->pluck('name')->all();

    expect($names)->toBe(['Beta']);
});

it('filters by set membership (whereIn)', function () {
    $names = gadgetResults(['filter' => ['status' => 'active,draft']])->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Alpha', 'Gamma']);
});

it('filters by a virtual search across declared columns', function () {
    $names = gadgetResults(['filter' => ['search' => 'red']])->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Alpha', 'Gamma']);
});

it('filters through a bound eloquent scope', function () {
    expect(gadgetResults(['filter' => ['flagged' => 1]])->pluck('name')->all())->toBe(['Alpha']);
});

it('reflects a declared sort into an AllowedSort', function () {
    $sorts = (new FilterReflector)->allowedSorts(GadgetFilterData::class);

    expect($sorts)->toHaveCount(1)
        ->and($sorts[0]->getName())->toBe('created_at');
});

it('reflects a declared include into an allowed include', function () {
    $includes = (new FilterReflector)->allowedIncludes(GadgetFilterData::class);

    expect(collect($includes)->map->getName())->toContain('parts');
});

it('picks up a host-defined operator without editing package internals', function () {
    $filters = (new FilterReflector)->allowedFilters(ShoutFilterData::class);

    expect($filters)->toHaveCount(1)
        ->and($filters[0]->getName())->toBe('code');
});
