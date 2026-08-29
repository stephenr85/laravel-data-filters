<?php

namespace Rushing\DataFilters;

use Rushing\DataFilters\Discovery\AttributedResourceFilterDiscovery;
use Rushing\DataFilters\Options\OptionsRegistry;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\Popcorn\Registries\RegistryIndex;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-data-filters')
            ->hasConfigFile('data-filters');
    }

    public function packageRegistered(): void
    {
        // No config snapshot here, deliberately: the registry seeds itself READ-THROUGH on its first
        // read or write (registry-kernel 38). `describe()` below forces this singleton to construct at
        // boot, and a constructor that took `config('data-filters.resources')` as an argument would
        // freeze the map at that moment — ahead of any host whose config materialises later.
        $this->app->singleton(ResourceRegistry::class, fn () => new ResourceRegistry);

        $this->app->singleton(OptionsRegistry::class, fn ($app) => new OptionsRegistry($app));

        $this->app->singleton(AttributedResourceFilterDiscovery::class, fn ($app) => new AttributedResourceFilterDiscovery(
            $app->make(ResourceRegistry::class)
        ));

        $this->app->singleton(DataFilterManager::class, fn ($app) => new DataFilterManager(
            $app->make(ResourceRegistry::class),
            $app,
            $app->make(OptionsRegistry::class),
        ));
    }

    public function packageBooted(): void
    {
        $this->registerSchemaStrategy();
        $this->discoverResourceFilters();
        $this->describeResourceRegistry();
        $this->describeOptionsRegistry();
    }

    /**
     * Make `data-filters.resources` routable in the shared popcorn index.
     *
     * Declaring and indexing are two acts (registry-kernel 21 D1): {@see ResourceRegistry} carries the
     * `#[IsRegistry]`, and this is where that root actually reaches {@see RegistryIndex} — until it
     * does, the index holds nothing and `new ExistsInRegistry('data-filters.resources')` has no
     * registry to validate against.
     *
     * LAST in `packageBooted()`, after discovery, so the described instance is the filled one. It is
     * described unconditionally and possibly empty: a host that declares no resources still owns the
     * branch.
     */
    protected function describeResourceRegistry(): void
    {
        $this->app->make(RegistryIndex::class)->describe(
            $this->app->make(ResourceRegistry::class),
            by: self::class,
        );
    }

    /**
     * Make `data-filters.options` routable in the shared popcorn index, for the same reason
     * {@see describeResourceRegistry()} does it for resources: declaring and indexing are two acts, and
     * an undeclared-or-unindexed registry is one `popcorn:registries` cannot show — so an agent looking
     * for where to register an options source finds `data-filters.resources`, nothing for options, and
     * builds a parallel mechanism beside this one.
     *
     * Described unconditionally and possibly empty: a host that registers no options source still owns
     * the branch.
     */
    protected function describeOptionsRegistry(): void
    {
        $this->app->make(RegistryIndex::class)->describe(
            $this->app->make(OptionsRegistry::class),
            by: self::class,
        );
    }

    /**
     * Register every `#[ResourceFilter]`-annotated class named (or reachable) from
     * `config('data-filters.discover')` — ADR-0008.
     *
     * Boot phase, deliberately: the registry was already seeded from
     * `config('data-filters.resources')` back in {@see packageRegistered()}, so a
     * config-declared resource is present before discovery ever runs and discovery's
     * `has()`-guard makes config win by construction. Both config keys default to `[]`,
     * so a host that hasn't opted in pays nothing and behaves exactly as before.
     */
    protected function discoverResourceFilters(): void
    {
        $classes = config('data-filters.discover.classes', []);
        $paths = config('data-filters.discover.paths', []);

        if ($classes === [] && $paths === []) {
            return;
        }

        $this->app->make(AttributedResourceFilterDiscovery::class)->discover($classes, $paths);
    }

    /**
     * Append the filterable-attributes strategy to the laravel-data-schemas
     * pipeline so `#[Filterable]`/`#[Sortable]` project to `x-filter`/`x-sort`
     * keywords (ADR-0001). Idempotent — guards against double-registration on
     * re-boot, the content-engine pattern.
     */
    protected function registerSchemaStrategy(): void
    {
        $strategies = config('data-schemas.strategies', []);

        if (! in_array(FilterableAttributesStrategy::class, $strategies, true)) {
            $strategies[] = FilterableAttributesStrategy::class;
            config(['data-schemas.strategies' => $strategies]);
        }
    }
}
