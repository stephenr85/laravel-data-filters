<?php

use Illuminate\Validation\ValidationException;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\SavedFilters\SavedFilter;
use Rushing\DataFilters\SavedFilters\SavedFilterValidator;
use Rushing\DataFilters\SavedFilters\Visibility;
use Rushing\DataFilters\Tests\Stubs\Widget;

function savedFilterValidator(): SavedFilterValidator
{
    return app(SavedFilterValidator::class);
}

it('persists a saved filter with a uuid pk, cast params, and visibility enum', function () {
    $saved = SavedFilter::create([
        'name' => 'Reds',
        'resource' => 'widget',
        'query_parameters' => ['filter' => ['color' => 'red']],
        'visibility' => Visibility::Shared,
    ]);

    $fresh = SavedFilter::find($saved->id);

    expect($fresh->id)->toBeString()
        ->and(strlen($fresh->id))->toBe(36)
        ->and($fresh->query_parameters)->toBe(['filter' => ['color' => 'red']])
        ->and($fresh->visibility)->toBe(Visibility::Shared);
});

it('accepts known filter/sort/include keys on save', function () {
    $params = savedFilterValidator()->validate('gadget', [
        'filter' => ['color' => 'red'],
        'sort' => '-created_at',
        'include' => 'parts',
    ]);

    expect($params['filter'])->toBe(['color' => 'red']);
});

it('rejects an unknown filter on save with a 422', function () {
    expect(fn () => savedFilterValidator()->validate('widget', ['filter' => ['bogus' => 'x']]))
        ->toThrow(ValidationException::class);
});

it('rejects an unknown sort on save', function () {
    expect(fn () => savedFilterValidator()->validate('gadget', ['sort' => 'nonsense']))
        ->toThrow(ValidationException::class);
});

it('rejects a wrong-typed value for a declared filter on save', function () {
    expect(fn () => savedFilterValidator()->validate('gadget', ['filter' => ['weight' => 'heavy']]))
        ->toThrow(ValidationException::class);

    expect(fn () => savedFilterValidator()->validate('gadget', ['filter' => ['flagged' => 'maybe']]))
        ->toThrow(ValidationException::class);

    expect(fn () => savedFilterValidator()->validate('gadget', ['filter' => ['status' => 'bogus']]))
        ->toThrow(ValidationException::class);
});

it('casts declared filter values to canonical form on save', function () {
    $params = savedFilterValidator()->validate('gadget', [
        'filter' => [
            'weight' => '42',
            'flagged' => '1',
            'status' => 'active',
        ],
    ]);

    expect($params['filter']['weight'])->toBe(42)
        ->and($params['filter']['flagged'])->toBe(true)
        ->and($params['filter']['status'])->toBe('active');
});

it('silently prunes a key the resource no longer allows on read', function () {
    $pruned = savedFilterValidator()->prune('widget', [
        'filter' => ['color' => 'red', 'bogus' => 'x'],
        'sort' => 'color,gone',
    ]);

    expect($pruned['filter'])->toBe(['color' => 'red'])
        ->and($pruned['sort'])->toBe('');
});

it('applies a saved filter to the same rows as inline params', function () {
    Widget::create(['name' => 'Alpha', 'color' => 'red']);
    Widget::create(['name' => 'Beta', 'color' => 'blue']);
    Widget::create(['name' => 'Gamma', 'color' => 'red']);

    $saved = SavedFilter::create([
        'name' => 'Reds',
        'resource' => 'widget',
        'query_parameters' => ['filter' => ['color' => 'red']],
    ]);

    $names = DataFilter::applySaved($saved)->get()->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Alpha', 'Gamma']);
});

it('drops a now-invalid key at apply time without failing', function () {
    Widget::create(['name' => 'Alpha', 'color' => 'red']);

    $saved = SavedFilter::create([
        'name' => 'Stale',
        'resource' => 'widget',
        'query_parameters' => ['filter' => ['color' => 'red', 'removed_field' => 'x']],
    ]);

    expect(DataFilter::applySaved($saved)->get()->pluck('name')->all())->toBe(['Alpha']);
});
