<?php

namespace Rushing\DataFilters\Query;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Reflection\FilterReflector;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The thin per-resource companion to a Filter Data class — the escape hatch for
 * what attributes can't express (ADR-0002). It binds the declared filter surface
 * to an Eloquent model and owns authorization scoping (override {@see baseQuery()}),
 * the default sort, and any imperative closure filter/sort/include. Concrete
 * classes keep a `*Query` suffix.
 *
 * The attribute-declared allowed-sets come from the Filter Data class via the
 * reflector; the imperative escape-hatch sets are merged on top.
 */
abstract class ResourceQuery
{
    public function __construct(
        protected ResourceDefinition $definition,
        protected FilterReflector $reflector,
    ) {}

    /**
     * The base Eloquent query the filters apply over. Override to apply row-level
     * authorization scoping before any user filter runs.
     */
    protected function baseQuery(Request $request): Builder
    {
        return ($this->definition->model)::query();
    }

    protected function defaultSort(): ?string
    {
        return null;
    }

    /**
     * Imperative filters that don't map to a declared property (closures, custom
     * scopes built at runtime).
     *
     * @return list<AllowedFilter>
     */
    protected function extraFilters(): array
    {
        return [];
    }

    /**
     * @return list<AllowedSort|string>
     */
    protected function extraSorts(): array
    {
        return [];
    }

    /**
     * @return list<AllowedInclude|string>
     */
    protected function extraIncludes(): array
    {
        return [];
    }

    /**
     * The full set of allowed filter keys — declared attributes plus the escape-hatch
     * `extraFilters()`. Used by saved-filter validation and pruning.
     *
     * @return list<string>
     */
    public function filterNames(): array
    {
        return $this->mergedNames(
            $this->reflector->filterNames($this->definition->data),
            $this->extraFilters(),
        );
    }

    /**
     * The declared filter key → backing Data property map (escape-hatch closure
     * filters have no Data property and are absent). The bridge saved-filter casting
     * uses to type a stored value against its declaration.
     *
     * @return array<string, \ReflectionProperty>
     */
    public function filterProperties(): array
    {
        return $this->reflector->filterProperties($this->definition->data);
    }

    /**
     * @return list<string>
     */
    public function sortNames(): array
    {
        return $this->mergedNames(
            $this->reflector->sortNames($this->definition->data),
            $this->extraSorts(),
        );
    }

    /**
     * @return list<string>
     */
    public function includeNames(): array
    {
        return $this->mergedNames(
            $this->reflector->includeNames($this->definition->data),
            $this->extraIncludes(),
        );
    }

    /**
     * @param  list<string>  $declared
     * @param  list<object|string>  $extra
     * @return list<string>
     */
    private function mergedNames(array $declared, array $extra): array
    {
        $extraNames = array_map(
            fn ($entry) => is_string($entry) ? $entry : $entry->getName(),
            $extra,
        );

        return array_values(array_unique([...$declared, ...$extraNames]));
    }

    /**
     * Apply only the declared (and escape-hatch) filters to a caller-provided base
     * query — no row-level auth scoping, no default sort. The seam for resolving a
     * resource's boolean boundary somewhere other than the list endpoint (e.g. a
     * retrieval policy's no-seed-query branch) while keeping one definition of how
     * the resource filters.
     */
    public function applyFiltersTo(Builder $base, Request $request): QueryBuilder
    {
        return QueryBuilder::for($base, $request)
            ->allowedFilters(...[
                ...$this->reflector->allowedFilters($this->definition->data),
                ...$this->extraFilters(),
            ]);
    }

    public function apply(Request $request): QueryBuilder
    {
        $data = $this->definition->data;

        $builder = QueryBuilder::for($this->baseQuery($request), $request)
            ->allowedFilters(...[
                ...$this->reflector->allowedFilters($data),
                ...$this->extraFilters(),
            ])
            ->allowedSorts(...[
                ...$this->reflector->allowedSorts($data),
                ...$this->extraSorts(),
            ])
            ->allowedIncludes(...[
                ...$this->reflector->allowedIncludes($data),
                ...$this->extraIncludes(),
            ]);

        if (($default = $this->defaultSort()) !== null) {
            $builder->defaultSort($default);
        }

        return $builder;
    }
}
