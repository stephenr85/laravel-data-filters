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
     * @param  array<string, array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}>  $resources
     */
    public function __construct(array $resources = [])
    {
        foreach ($resources as $key => $config) {
            $this->register($key, $config);
        }
    }

    /**
     * @param  array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}  $config
     */
    public function register(string $key, array $config): void
    {
        $this->registerDefinition(ResourceDefinition::fromConfig($key, $config));
    }

    /**
     * Register an already-built definition. Attribute discovery registers this way (it
     * reflects a definition rather than assembling a config array), and like
     * {@see register()} it overwrites plainly — the `has()`-guard that makes discovery
     * strictly additive lives at the caller, not here (ADR-0008).
     */
    public function registerDefinition(ResourceDefinition $definition): void
    {
        $this->resources[$definition->key] = $definition;
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
