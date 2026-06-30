<?php

declare(strict_types=1);

namespace Rushing\DataFilters;

use Illuminate\Contracts\Container\Container;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;

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
}
