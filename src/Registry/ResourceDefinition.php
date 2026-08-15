<?php

namespace Rushing\DataFilters\Registry;

use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\DataFilterManager;

/**
 * The wiring behind one resource key: the Filter Data class (declaration site), the
 * Query class (escape hatch + model binding), and the Eloquent model. Bound classes
 * self-describe; this is the value the Resource Registry resolves a key to.
 *
 * `$model` is nullable because an attribute-declared resource may omit it and lean on the
 * host's {@see ResourceModelResolver} (ADR-0008); a definition reaching a Query class has
 * always been through {@see DataFilterManager::resource()} and so
 * carries a concrete one. Read it through {@see requireModel()} rather than the property
 * when you need the guarantee.
 */
class ResourceDefinition
{
    /**
     * The canonical key this definition resolves its model under — its own `key` unless it
     * was registered as an alias of another resource.
     */
    public string $resource;

    /**
     * @param  class-string  $data
     * @param  class-string  $query
     * @param  class-string|null  $model
     */
    public function __construct(
        public string $key,
        public string $data,
        public string $query,
        public ?string $model = null,
        ?string $resource = null,
    ) {
        $this->resource = $resource ?? $key;
    }

    /**
     * @param  array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            data: $config['data'],
            query: $config['query'],
            model: $config['model'] ?? null,
            resource: $config['resource'] ?? null,
        );
    }

    /**
     * A copy of this definition bound to a concrete model — how a lazily-resolved
     * `model` is folded in without mutating the registered declaration.
     *
     * @param  class-string  $model
     */
    public function withModel(string $model): self
    {
        return new self(
            key: $this->key,
            data: $this->data,
            query: $this->query,
            model: $model,
            resource: $this->resource,
        );
    }

    /**
     * @return class-string
     */
    public function requireModel(): string
    {
        return $this->model ?? throw UnresolvableResourceModel::unresolvedFor($this);
    }
}
