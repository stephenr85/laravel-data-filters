<?php

use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryIndex;

/*
 * Registry-kernel ticket 38: `data-filters.resources` on the popcorn kernel.
 *
 * The tripwire first (27 D3): testbench does not auto-discover, so a harness that omits
 * `PopcornServiceProvider` hands every `make()` a FRESH index — `describe()` lands on a throwaway and
 * every assertion below passes over an empty one. Assert the sharing before believing anything else.
 */

it('shares one RegistryIndex across the container', function () {
    expect(app(RegistryIndex::class))->toBe(app(RegistryIndex::class));
});

it('conforms to the kernel contract', function () {
    expect(app(ResourceRegistry::class))->toBeInstanceOf(Registry::class);
});

it('describes its root into the shared index at boot', function () {
    $roots = array_map(strval(...), app(RegistryIndex::class)->keys());

    expect($roots)->toContain('data-filters.resources');
});

it('routes an absolute resource key back to the registry', function () {
    expect(app(RegistryIndex::class)->routeTo('data-filters.resources.widget'))
        ->toBe(app(ResourceRegistry::class));
});

it('keeps the port vocabulary over the kernel — register, has, get, all', function () {
    $registry = app(ResourceRegistry::class);

    // The config seed arrived read-through, keyed as the host spelled it.
    expect($registry->has('widget'))->toBeTrue()
        ->and($registry->get('widget'))->toBeInstanceOf(ResourceDefinition::class)
        ->and($registry->get('widget')->query)->toBe(WidgetQuery::class)
        ->and(array_keys($registry->all()))->toBe(['widget', 'gadget', 'divergent']);

    // A round trip through the two-argument config form the facade and every host use.
    $registry->register('sprocket', [
        'data' => WidgetFilterData::class,
        'query' => WidgetQuery::class,
        'model' => Widget::class,
    ]);

    expect($registry->has('sprocket'))->toBeTrue()
        ->and($registry->get('sprocket')->model)->toBe(Widget::class)
        ->and($registry->all())->toHaveKey('sprocket');

    // And through the self-keying definition form attribute discovery uses.
    $registry->registerDefinition(new ResourceDefinition(
        key: 'cog',
        data: WidgetFilterData::class,
        query: WidgetQuery::class,
        model: Widget::class,
    ));

    expect($registry->get('cog')->key)->toBe('cog');

    // Relative in, absolute out (20 D2).
    expect(array_map(strval(...), $registry->keys()))
        ->toContain('data-filters.resources.sprocket', 'data-filters.resources.cog');
});

it('answers a miss with the kernel exception', function () {
    expect(fn () => app(ResourceRegistry::class)->get('nope'))->toThrow(RegistryMiss::class);
    expect(app(ResourceRegistry::class)->tryResolve('nope'))->toBeNull();
});

it('seeds read-through, so config that lands after boot is still honoured', function () {
    // The archetype-c trap, asserted rather than narrated: `describe()` forces this singleton to
    // construct at boot, and a constructor snapshot would freeze the map there.
    config(['data-filters.resources.late' => [
        'data' => WidgetFilterData::class,
        'query' => WidgetQuery::class,
        'model' => Widget::class,
    ]]);

    app()->forgetInstance(ResourceRegistry::class);

    expect(app(ResourceRegistry::class)->has('late'))->toBeTrue();
});

it('exposes the same registry through the facade', function () {
    expect(DataFilter::registry())->toBe(app(ResourceRegistry::class))
        ->and(array_keys(DataFilter::registry()->all()))->toContain('widget');
});
