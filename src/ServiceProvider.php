<?php

namespace Rushing\DataFilters;

use Rushing\DataFilters\Options\OptionsRegistry;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
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
        $this->app->singleton(ResourceRegistry::class, fn () => new ResourceRegistry(
            config('data-filters.resources', [])
        ));

        $this->app->singleton(OptionsRegistry::class, fn ($app) => new OptionsRegistry($app));

        $this->app->singleton(DataFilterManager::class, fn ($app) => new DataFilterManager(
            $app->make(ResourceRegistry::class),
            $app,
            $app->make(OptionsRegistry::class),
        ));
    }

    public function packageBooted(): void
    {
        $this->registerSchemaStrategy();
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
