<?php

use Rushing\DataFilters\Facades\DataFilter;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Tests\Stubs\Discovery\DiscoveredWidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\GadgetQuery;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;

/**
 * Discovery through the provider's own boot phase — the path a host actually gets, where
 * the ordering that makes the override precedence hold (config seeds in `packageRegistered`,
 * discovery runs in `packageBooted`) is real rather than arranged by the test.
 */
it('registers attribute-declared resources during boot', function () {
    $definition = DataFilter::resource('discovered_widget');

    expect($definition->data)->toBe(DiscoveredWidgetFilterData::class)
        ->and($definition->query)->toBe(WidgetQuery::class);
});

it('registers path-scanned resources during boot, including alias keys', function () {
    expect(DataFilter::resource('gizmos')->query)->toBe(GadgetQuery::class)
        ->and(DataFilter::resource('gizmo')->query)->toBe(GadgetQuery::class);
});

it('lets the config-seeded wiring win over a discovered declaration of the same key', function () {
    $definition = DataFilter::resource('widget');

    expect($definition->query)->toBe(WidgetQuery::class)
        ->and($definition->model)->toBe(Widget::class);
});

it('resolves a lazily-declared model through the host-bound resolver', function () {
    expect(DataFilter::resource('gizmos')->model)->toBe(Gadget::class);
});

it('keeps a null-query declaration out of the registry through the boot path too', function () {
    expect(app(ResourceRegistry::class)->has('unwired'))->toBeFalse();
});
