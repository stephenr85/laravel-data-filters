<?php

namespace Rushing\DataFilters\Registry;

use InvalidArgumentException;

/**
 * The runtime map of resource key → wiring (ADR-0002). Seeded from
 * `config('data-filters.resources')` and augmentable at runtime via the
 * `DataFilter` facade. A stable string key is how list endpoints, saved filters,
 * and the schema strategy all resolve the same resource.
 */
class ResourceRegistry
{
    /** @var array<string, ResourceDefinition> */
    private array $resources = [];

    /**
     * @param  array<string, array{data: class-string, query: class-string, model: class-string}>  $resources
     */
    public function __construct(array $resources = [])
    {
        foreach ($resources as $key => $config) {
            $this->register($key, $config);
        }
    }

    /**
     * @param  array{data: class-string, query: class-string, model: class-string}  $config
     */
    public function register(string $key, array $config): void
    {
        $this->resources[$key] = ResourceDefinition::fromConfig($key, $config);
    }

    public function has(string $key): bool
    {
        return isset($this->resources[$key]);
    }

    public function get(string $key): ResourceDefinition
    {
        return $this->resources[$key] ?? throw new InvalidArgumentException(
            "No data-filters resource registered for key [{$key}]."
        );
    }

    /**
     * @return array<string, ResourceDefinition>
     */
    public function all(): array
    {
        return $this->resources;
    }
}
