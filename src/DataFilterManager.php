<?php

namespace Rushing\DataFilters;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\Options\OptionsRegistry;
use Rushing\DataFilters\Options\OptionsSource;
use Rushing\DataFilters\Query\ResourceQuery;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\DataFilters\Registry\UnresolvableResourceModel;
use Rushing\DataFilters\SavedFilters\SavedFilter;
use Rushing\DataFilters\SavedFilters\SavedFilterValidator;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The façade target. Owns the Resource Registry and resolves a resource key to a
 * built Query class. A host registers resources here imperatively when config-file
 * registration isn't enough; otherwise the registry is seeded from
 * `config('data-filters.resources')`.
 */
class DataFilterManager
{
    public function __construct(
        private ResourceRegistry $registry,
        private Container $container,
        private OptionsRegistry $options,
    ) {}

    public function registry(): ResourceRegistry
    {
        return $this->registry;
    }

    /**
     * Register a host Options Source for a relational filter's `optionsRef`
     * (ADR-0006). The package owns no HTTP route; the host delivers the values.
     *
     * @param  OptionsSource|class-string<OptionsSource>|Closure  $source
     */
    public function options(string $key, OptionsSource|string|Closure $source): void
    {
        $this->options->register($key, $source);
    }

    public function hasOptions(string $key): bool
    {
        return $this->options->has($key);
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public function resolveOptions(string $key, ?string $search = null): array
    {
        return $this->options->resolve($key, $search);
    }

    /**
     * Resolve (or, given $config, register-then-resolve) a resource's wiring. This is also
     * where a lazily-declared model is resolved — see {@see resolveModel()}.
     *
     * @param  array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}|null  $config
     */
    public function resource(string $key, ?array $config = null): ResourceDefinition
    {
        if ($config !== null) {
            $this->registry->register($key, $config);
        }

        return $this->resolveModel($this->registry->get($key));
    }

    /**
     * {@see resource()}'s nullable twin — `null` when no resource is registered under that key, or
     * when the key is not a legal registry key at all.
     *
     * **Reach for this whenever the key came off a request** (registry-kernel ticket 61). A miss on a
     * user-supplied path segment is an ordinary 404, and `resource()` answers it with a `RegistryMiss`
     * that a controller has no business catching.
     *
     * A resource that IS registered but whose model cannot be resolved still throws
     * {@see UnresolvableResourceModel} — that is a misconfiguration of a resource that exists, not an
     * unknown key, and flattening it into `null` would answer 404 for a wiring bug.
     */
    public function tryResource(string $key): ?ResourceDefinition
    {
        $definition = $this->registry->find($key);

        return $definition === null ? null : $this->resolveModel($definition);
    }

    /**
     * Build the per-resource Query class, with its definition bound and the
     * reflector injected.
     */
    public function query(string $key): ResourceQuery
    {
        $definition = $this->resource($key);

        return $this->container->make($definition->query, [
            'definition' => $definition,
        ]);
    }

    /**
     * Bind a concrete model onto a definition that declared none, via the host's
     * {@see ResourceModelResolver} (ADR-0008).
     *
     * This runs HERE, at resource-resolution time, and not at discovery or boot time on
     * purpose: a `#[ResourceFilter]` may well be discovered before whatever host registry
     * knows its model has finished registering, and making that ordering load-bearing is
     * exactly the class of silent-misconfiguration bug the attribute exists to remove.
     * By the time a caller asks for a resource, every provider has booted.
     */
    private function resolveModel(ResourceDefinition $definition): ResourceDefinition
    {
        if ($definition->model !== null) {
            return $definition;
        }

        if (! $this->container->bound(ResourceModelResolver::class)) {
            throw UnresolvableResourceModel::unboundResolver($definition);
        }

        $model = $this->container->make(ResourceModelResolver::class)
            ->resolveModel($definition->resource);

        if ($model === null) {
            throw UnresolvableResourceModel::resolverReturnedNull($definition);
        }

        return $definition->withModel($model);
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
