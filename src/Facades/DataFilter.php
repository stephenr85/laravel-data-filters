<?php

namespace Rushing\DataFilters\Facades;

use Illuminate\Support\Facades\Facade;
use Rushing\DataFilters\DataFilterManager;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;

/**
 * @method static ResourceRegistry registry()
 * @method static ResourceDefinition resource(string $key, ?array $config = null)
 * @method static ResourceQuery query(string $key)
 * @method static void options(string $key, \Rushing\DataFilters\Options\OptionsSource|string|\Closure $source)
 * @method static bool hasOptions(string $key)
 * @method static array resolveOptions(string $key, ?string $search = null)
 *
 * @see DataFilterManager
 */
class DataFilter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataFilterManager::class;
    }
}
