<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Rushing\DataFilters\Operators\NullableExact;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Spatie\QueryBuilder\QueryBuilder;

beforeEach(function () {
    Schema::table('widgets', fn (Blueprint $table) => $table->unsignedBigInteger('parent_id')->nullable());
    Widget::create(['name' => 'Root', 'color' => 'blue', 'parent_id' => null]);
    Widget::create(['name' => 'Child', 'color' => 'blue', 'parent_id' => 7]);
    Widget::create(['name' => 'Other child', 'color' => 'blue', 'parent_id' => 8]);
});

it('does not constrain a query when the nullable filter is absent', function () {
    $query = QueryBuilder::for(Widget::class, Request::create('/widgets'))
        ->allowedFilters((new NullableExact(column: 'parent_id'))->toAllowedFilter('parentId', 'parent_id'));
    expect($query->count())->toBe(3);
});

it('selects only roots for an explicitly empty or middleware-normalized null filter', function ($value) {
    $query = QueryBuilder::for(Widget::class, Request::create('/widgets', 'GET', ['filter' => ['parentId' => $value]]))
        ->allowedFilters((new NullableExact(column: 'parent_id'))->toAllowedFilter('parentId', 'parent_id'));
    expect($query->pluck('name')->all())->toBe(['Root']);
})->with(['empty string' => '', 'normalized null' => null]);

it('keeps nonnull parent filtering exact', function () {
    $query = QueryBuilder::for(Widget::class, Request::create('/widgets', 'GET', ['filter' => ['parentId' => 7]]))
        ->allowedFilters((new NullableExact(column: 'parent_id'))->toAllowedFilter('parentId', 'parent_id'));
    expect($query->pluck('name')->all())->toBe(['Child']);
});
