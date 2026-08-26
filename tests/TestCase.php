<?php

namespace Rushing\DataFilters\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\DataFilters\ServiceProvider;
use Rushing\DataFilters\Tests\Stubs\DivergentSortFilterData;
use Rushing\DataFilters\Tests\Stubs\DivergentSortQuery;
use Rushing\DataFilters\Tests\Stubs\Gadget;
use Rushing\DataFilters\Tests\Stubs\GadgetFilterData;
use Rushing\DataFilters\Tests\Stubs\GadgetQuery;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\QueryBuilder\QueryBuilderServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            // laravel-popcorn binds RegistryIndex as a SINGLETON. Without it testbench hands every
            // `make()` a fresh index, so `describe()` lands on a throwaway and every index assertion
            // below would pass over an empty one (registry-kernel 27 D3). Testbench does not
            // auto-discover, so requiring the package is not enough — it has to be named here.
            PopcornServiceProvider::class,
            LaravelDataSchemasServiceProvider::class,
            QueryBuilderServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('data-filters.resources', [
            'widget' => [
                'data' => WidgetFilterData::class,
                'query' => WidgetQuery::class,
                'model' => Widget::class,
            ],
            'gadget' => [
                'data' => GadgetFilterData::class,
                'query' => GadgetQuery::class,
                'model' => Gadget::class,
            ],
            'divergent' => [
                'data' => DivergentSortFilterData::class,
                'query' => DivergentSortQuery::class,
                'model' => Gadget::class,
            ],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color');
            $table->timestamps();
        });

        Schema::create('gadgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color');
            $table->integer('weight')->default(0);
            $table->string('status')->default('draft');
            $table->boolean('flagged')->default(false);
            $table->timestamps();
        });

        Schema::create('saved_filters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('resource');
            $table->json('query_parameters');
            $table->nullableUuidMorphs('owner');
            $table->string('visibility')->default('private');
            $table->nullableUuidMorphs('context');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }
}
