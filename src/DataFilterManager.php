<?php

declare(strict_types=1);

namespace Rushing\DataFilters;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\SavedFilters\SavedFilter;
use Rushing\DataFilters\SavedFilters\SavedFilterValidator;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The façade target. Owns the Resource Registry and resolves a resource key to a
 * built Query class. A host registers resources here imperatively when config-file
 * registration isn't enough; otherwise the registry is seeded from
 * `config('data-filters.resources')`.
 */
final class DataFilterManager
{
    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly Container $container,
    ) {}

    public function registry(): ResourceRegistry
    {
        return $this->registry;
    }

    /**
     * Resolve (or, given $config, register-then-resolve) a resource's wiring.
     *
     * @param  array{data: class-string, query: class-string, model: class-string}|null  $config
     */
    public function resource(string $key, ?array $config = null): ResourceDefinition
    {
        if ($config !== null) {
            $this->registry->register($key, $config);
        }

        return $this->registry->get($key);
    }

    /**
     * Build the per-resource Query class, with its definition bound and the
     * reflector injected.
     */
    public function query(string $key): ResourceQuery
    {
        $definition = $this->registry->get($key);

        return $this->container->make($definition->query, [
            'definition' => $definition,
        ]);
    }

    /**
     * Apply a saved filter: prune its stored params against the *current* resource
     * (ADR-0007), rebuild the equivalent request, and return the same QueryBuilder a
     * client would get by passing those params inline.
     */
    public function applySaved(SavedFilter $filter): QueryBuilder
    {
        $params = $this->container->make(SavedFilterValidator::class)
            ->prune($filter->resource, $filter->query_parameters ?? []);

        return $this->query($filter->resource)->apply($this->requestFromParams($params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function requestFromParams(array $params): Request
    {
        $query = [];

        if (! empty($params['filter'])) {
            $query['filter'] = $params['filter'];
        }
        if (! empty($params['sort'])) {
            $query['sort'] = is_array($params['sort']) ? implode(',', $params['sort']) : $params['sort'];
        }
        if (! empty($params['include'])) {
            $query['include'] = is_array($params['include']) ? implode(',', $params['include']) : $params['include'];
        }

        return Request::create('/', 'GET', $query);
    }
}
