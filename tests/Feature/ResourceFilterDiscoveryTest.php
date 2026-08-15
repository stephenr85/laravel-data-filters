<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\Discovery\AttributedResourceFilterDiscovery;
use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Registry\UnresolvableResourceModel;
use Rushing\DataFilters\Tests\Stubs\Discovery\AliasedGizmoFilterData;
use Rushing\DataFilters\Tests\Stubs\Discovery\DiscoveredWidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\Discovery\UnwiredFilterData;
use Rushing\DataFilters\Tests\Stubs\DiscoveryConflicts\ConfigOverriddenWidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\DiscoveryConflicts\ConflictingGizmoFilterData;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\GadgetQuery;
use Rushing\DataFilters\Tests\Stubs\StubModelResolver;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;

/**
 * `#[ResourceFilter]` discovery (ADR-0008) — a resource registering itself from the
 * declaration site, instead of through a hand-maintained `config('data-filters.resources')`
 * entry nothing enforced the existence of.
 */
function discovery(): AttributedResourceFilterDiscovery
{
    return new AttributedResourceFilterDiscovery(app(ResourceRegistry::class));
}

it('reflects every instance of the repeatable attribute, not just the first', function () {
    $attributes = (new ReflectionClass(AliasedGizmoFilterData::class))
        ->getAttributes(ResourceFilter::class);

    expect($attributes)->toHaveCount(2);

    $instances = array_map(fn ($a) => $a->newInstance(), $attributes);

    expect(array_column($instances, 'key'))->toBe(['gizmos', 'gizmo']);
});

it('registers a discovered class whose declaration carries a query', function () {
    discovery()->discover(classes: [DiscoveredWidgetFilterData::class]);

    expect(app(ResourceRegistry::class)->has('discovered_widget'))->toBeTrue();

    $definition = DataFilter::resource('discovered_widget');

    expect($definition->data)->toBe(DiscoveredWidgetFilterData::class)
        ->and($definition->query)->toBe(WidgetQuery::class)
        ->and($definition->model)->toBe(Widget::class);
});

it('registers nothing for a declaration whose query is null', function () {
    discovery()->discover(classes: [UnwiredFilterData::class]);

    expect(app(ResourceRegistry::class)->has('unwired'))->toBeFalse();
});

it('leaves the imperative escape hatch to complete a null-query declaration', function () {
    discovery()->discover(classes: [UnwiredFilterData::class]);

    DataFilter::resource('unwired', [
        'data' => UnwiredFilterData::class,
        'query' => WidgetQuery::class,
        'model' => Widget::class,
    ]);

    expect(DataFilter::resource('unwired')->query)->toBe(WidgetQuery::class);
});

it('never overwrites a config-seeded key', function () {
    // `widget` is seeded from config in the test harness, wired to WidgetQuery/Widget.
    discovery()->discover(classes: [ConfigOverriddenWidgetFilterData::class]);

    $definition = DataFilter::resource('widget');

    expect($definition->query)->toBe(WidgetQuery::class)
        ->and($definition->model)->toBe(Widget::class);
});

it('registers both keys of a repeatable alias declaration against the same wiring', function () {
    app()->instance(ResourceModelResolver::class, new StubModelResolver(['gizmos' => Gadget::class]));

    discovery()->discover(classes: [AliasedGizmoFilterData::class]);

    $canonical = DataFilter::resource('gizmos');
    $alias = DataFilter::resource('gizmo');

    expect($canonical->query)->toBe($alias->query)
        ->and($canonical->data)->toBe($alias->data)
        ->and($canonical->model)->toBe(Gadget::class)
        // The alias resolves its model under the key it aliases, not under its own.
        ->and($alias->model)->toBe(Gadget::class)
        ->and($alias->resource)->toBe('gizmos');
});

it('fails with a message naming the missing seam when no resolver is bound', function () {
    discovery()->discover(classes: [AliasedGizmoFilterData::class]);

    expect(fn () => DataFilter::resource('gizmos'))
        ->toThrow(
            UnresolvableResourceModel::class,
            'Resource [gizmos] declares no `model` and no '.ResourceModelResolver::class.' is bound.',
        );
});

it('fails distinctly when the resolver is bound but does not know the key', function () {
    app()->instance(ResourceModelResolver::class, new StubModelResolver);

    discovery()->discover(classes: [AliasedGizmoFilterData::class]);

    expect(fn () => DataFilter::resource('gizmos'))
        ->toThrow(UnresolvableResourceModel::class, 'returned null for the key [gizmos]');
});

it('resolves the model at resolution time, so a resolver bound after discovery still works', function () {
    discovery()->discover(classes: [AliasedGizmoFilterData::class]);

    // Bound AFTER discovery has already run — the whole point of deferring resolution.
    app()->instance(ResourceModelResolver::class, new StubModelResolver(['gizmos' => Gadget::class]));

    expect(DataFilter::resource('gizmos')->model)->toBe(Gadget::class);
});

it('logs a debug warning and keeps the first registration when two classes claim one key', function () {
    Log::shouldReceive('debug')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'Duplicate #[ResourceFilter] key [gizmos]')
            && str_contains($message, ConflictingGizmoFilterData::class));

    discovery()->discover(classes: [AliasedGizmoFilterData::class, ConflictingGizmoFilterData::class]);

    expect(app(ResourceRegistry::class)->get('gizmos')->query)->toBe(GadgetQuery::class);
});

it('scans a directory path for annotated classes', function () {
    discovery()->discover(paths: [__DIR__.'/../Stubs/Discovery']);

    $registry = app(ResourceRegistry::class);

    expect($registry->has('discovered_widget'))->toBeTrue()
        ->and($registry->has('gizmos'))->toBeTrue()
        ->and($registry->has('gizmo'))->toBeTrue()
        // The null-query declaration under the same path is reflected but registers nothing.
        ->and($registry->has('unwired'))->toBeFalse();
});

it('rejects an explicitly-listed class that carries no declaration', function () {
    expect(fn () => discovery()->discover(classes: [Widget::class]))
        ->toThrow(InvalidArgumentException::class, 'carries no #[ResourceFilter]');
});

it('discovers nothing by default, leaving every existing host untouched', function () {
    expect(config('data-filters.discover.classes'))->toBe([])
        ->and(config('data-filters.discover.paths'))->toBe([])
        // The config-seeded resources of a host that never opted in still resolve.
        ->and(DataFilter::resource('widget')->model)->toBe(Widget::class)
        ->and(app(ResourceRegistry::class)->has('discovered_widget'))->toBeFalse();
});

it('builds a working query off a purely attribute-declared resource', function () {
    discovery()->discover(classes: [DiscoveredWidgetFilterData::class]);

    Widget::create(['name' => 'alpha', 'color' => 'red']);
    Widget::create(['name' => 'beta', 'color' => 'blue']);

    $request = Request::create('/', 'GET', ['filter' => ['color' => 'red']]);

    expect(DataFilter::query('discovered_widget')->apply($request)->pluck('name')->all())
        ->toBe(['alpha']);
});
