<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Facades;

use Illuminate\Support\Facades\Facade;
use Rushing\DataFilters\DataFilterManager;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Query\ResourceQuery;

/**
 * @method static ResourceRegistry registry()
 * @method static ResourceDefinition resource(string $key, ?array $config = null)
 * @method static ResourceQuery query(string $key)
 *
 * @see DataFilterManager
 */
final class DataFilter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DataFilterManager::class;
    }
}
