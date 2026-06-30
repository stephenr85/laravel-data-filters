<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\DataFilters\ServiceProvider;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;
use Rushing\DataFilters\Tests\Stubs\WidgetQuery;
use Rushing\DataFilters\Tests\Stubs\Widget;
use Rushing\LaravelDataSchemas\LaravelDataSchemasServiceProvider;
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
    }
}
