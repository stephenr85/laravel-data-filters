<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Query;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Rushing\DataFilters\Reflection\FilterReflector;
use Rushing\DataFilters\Registry\ResourceDefinition;
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
        protected readonly ResourceDefinition $definition,
        protected readonly FilterReflector $reflector,
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
     * @return list<\Spatie\QueryBuilder\AllowedFilter>
     */
    protected function extraFilters(): array
    {
        return [];
    }

    /**
     * @return list<\Spatie\QueryBuilder\AllowedSort|string>
     */
    protected function extraSorts(): array
    {
        return [];
    }

    /**
     * @return list<\Spatie\QueryBuilder\AllowedInclude|string>
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
