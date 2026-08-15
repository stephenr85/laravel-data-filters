<?php

namespace Rushing\DataFilters\Tests;

use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\Tests\Stubs\Discovery\DiscoveredWidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\DiscoveryConflicts\ConfigOverriddenWidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\StubModelResolver;

/**
 * The harness for the real boot path: `config('data-filters.discover')` populated BEFORE
 * the provider boots, so discovery runs where a host would actually get it — not from a
 * hand-instantiated discoverer. It opts in both ways at once (an explicit class and a
 * scanned path) and deliberately points discovery at a class that claims the
 * config-seeded `widget` key, so the override precedence is proven through the provider.
 */
abstract class BootDiscoveryTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('data-filters.discover', [
            'classes' => [
                DiscoveredWidgetFilterData::class,
                ConfigOverriddenWidgetFilterData::class,
            ],
            'paths' => [__DIR__.'/Stubs/Discovery'],
        ]);

        $app->instance(ResourceModelResolver::class, new StubModelResolver([
            'gizmos' => Gadget::class,
        ]));
    }
}
