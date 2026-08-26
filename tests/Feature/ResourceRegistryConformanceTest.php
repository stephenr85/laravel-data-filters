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

/*
 * Registry-kernel ticket 61: the port publishes BOTH halves of the miss pair, not just the throwing
 * one. `get()`/`resource()` throw; `find()`/`tryResource()` return null. A host reading a key it took
 * off a request uses the nullable half — otherwise a `RegistryMiss` becomes a 500 where a 404 was
 * asserted, which is exactly what conforming this registry did to the flagship.
 */

it('publishes a nullable twin for a key that came from outside', function () {
    $registry = app(ResourceRegistry::class);

    expect($registry->find('widget'))->toBeInstanceOf(ResourceDefinition::class)
        ->and($registry->find('nope'))->toBeNull();

    // And the shape half of the same problem: a string that is not a legal key at all is a miss here,
    // not the `InvalidRegistryKey` the kernel's parser (rightly) raises at a declaration site.
    expect($registry->find('Widget'))->toBeNull()
        ->and($registry->find('not a key'))->toBeNull()
        ->and($registry->find('a/b'))->toBeNull()
        ->and($registry->find(''))->toBeNull();
});

it('carries the nullable twin up through the manager and the facade', function () {
    expect(DataFilter::tryResource('widget'))->toBeInstanceOf(ResourceDefinition::class)
        ->and(DataFilter::tryResource('nope'))->toBeNull()
        ->and(DataFilter::tryResource('Nope!'))->toBeNull()
        ->and(fn () => DataFilter::resource('nope'))->toThrow(RegistryMiss::class);
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
