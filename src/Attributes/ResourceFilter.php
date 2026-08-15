<?php

namespace Rushing\DataFilters\Attributes;

use Attribute;
use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\Discovery\AttributedResourceFilterDiscovery;
use Rushing\DataFilters\Query\ResourceQuery;

/**
 * Declares a Resource on the class that carries it — the attribute twin of a
 * hand-maintained `config('data-filters.resources')` entry (ADR-0008). Placed on the
 * Filter Data class: the dedicated `*FilterData` class under the two-class convention
 * (ADR-0002), or stacked alongside a host's own resource attribute on a single-class
 * resource. The annotated class IS the Resource's `data`.
 *
 *   #[ResourceFilter(key: 'articles', query: ArticleQuery::class)]
 *   #[ResourceFilter(key: 'article', resource: 'articles', query: ArticleQuery::class)]  // legacy singular alias
 *   class ArticleFilterData extends Data { … }
 *
 * The attribute is **repeatable**, and that is load-bearing: a legacy alias key is just a
 * second declaration on the same class sharing a `resource`, which is why there is no
 * dedicated `alias:` parameter.
 *
 * Parameters:
 *  - `key` — the Resource Registry key this declaration registers under.
 *  - `resource` — the *canonical* key this declaration is an alias of; defaults to `key`.
 *     It is the key {@see ResourceModelResolver} is asked about when `model` is omitted.
 *  - `model` — the Eloquent model. Omit it to resolve lazily through the host's bound
 *     {@see ResourceModelResolver} at resource-resolution time, so a Filter Data class
 *     needn't restate a model its sibling resource declaration already names.
 *  - `query` — the {@see ResourceQuery} subclass. `null` means
 *     "declared, but a host must still complete the wiring": discovery reflects the
 *     declaration and registers nothing, leaving the imperative
 *     `DataFilter::resource($key, [...])` escape hatch to supply the concrete Query class.
 *     That is the shape for a package that declares a resource whose Query class lives in
 *     a different host app entirely.
 *
 * **Override precedence** — three tiers, and discovery is deliberately the weakest:
 *  1. `config('data-filters.resources')` seeds the registry in `packageRegistered()` and
 *     therefore always wins by construction.
 *  2. `#[ResourceFilter]` discovery runs in `packageBooted()` and is `has()`-guarded — it
 *     never overwrites a config-seeded key or an earlier discovery pass.
 *  3. Imperative `DataFilter::resource($key, [...])` overwrites unconditionally, whenever
 *     it is called. It remains the last word.
 *
 * @see AttributedResourceFilterDiscovery the reflect→register machinery behind this attribute
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class ResourceFilter
{
    /**
     * @param  class-string|null  $model
     * @param  class-string<ResourceQuery>|null  $query
     */
    public function __construct(
        public string $key,
        public ?string $resource = null,
        public ?string $model = null,
        public ?string $query = null,
    ) {}

    /**
     * The canonical key this declaration resolves its model under — its own `key` unless
     * it declares itself an alias of another.
     */
    public function resourceKey(): string
    {
        return $this->resource ?? $this->key;
    }
}
