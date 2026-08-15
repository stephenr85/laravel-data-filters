<?php

namespace Rushing\DataFilters\Reflection;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Includable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\Enums\SortDirection;

/**
 * Reflects a Filter Data class's declared surface into the spatie/laravel-query-builder
 * allowed-sets. The same `#[Filterable]` attributes this reads are projected to
 * `x-filter` keywords by {@see FilterableAttributesStrategy};
 * one declaration site, two derived artifacts (ADR-0001).
 *
 * The default sort has three accessors rather than one because its consumers can't take the
 * same shape. {@see defaultSort()} — the original — returns a sign-prefixed string, which is
 * structurally unable to carry a `#[Sortable]`'s `name → column` mapping: a consumer
 * rebuilding an `AllowedSort` from that string loses the column and orders by the sort key,
 * a column that may not exist. It stays for the `name === column` case and is deprecated.
 * {@see defaultAllowedSort()} returns a real `AllowedSort` with the mapping intact, for a
 * spatie/query-builder consumer; {@see defaultSortColumn()} returns a raw
 * `['column', 'direction']` pair, for a consumer ordering a plain Eloquent builder that has
 * nowhere to put an `AllowedSort`.
 */
class FilterReflector
{
    /**
     * @param  class-string  $dataClass
     * @return list<AllowedFilter>
     */
    public function allowedFilters(string $dataClass): array
    {
        $filters = [];

        foreach ($this->properties($dataClass) as $property) {
            $attribute = $this->attribute($property, Filterable::class);
            if ($attribute === null) {
                continue;
            }

            $name = $attribute->name ?? Str::snake($property->getName());
            $filters[] = $attribute->operator()->toAllowedFilter($name);
        }

        return $filters;
    }

    /**
     * @param  class-string  $dataClass
     * @return list<AllowedSort>
     */
    public function allowedSorts(string $dataClass): array
    {
        $sorts = [];

        foreach ($this->properties($dataClass) as $property) {
            $attribute = $this->attribute($property, Sortable::class);
            if ($attribute === null) {
                continue;
            }

            $name = $attribute->name ?? Str::snake($property->getName());
            $sorts[] = $attribute->column === null
                ? AllowedSort::field($name)
                : AllowedSort::field($name, $attribute->column);
        }

        return $sorts;
    }

    /**
     * @param  class-string  $dataClass
     * @return list<AllowedInclude>
     */
    public function allowedIncludes(string $dataClass): array
    {
        $includes = [];

        foreach ($this->properties($dataClass) as $property) {
            $attribute = $this->attribute($property, Includable::class);
            if ($attribute === null) {
                continue;
            }

            $name = $attribute->name ?? Str::snake($property->getName());
            $relationship = AllowedInclude::relationship($name);

            foreach (is_iterable($relationship) ? $relationship : [$relationship] as $include) {
                $includes[] = $include;
            }
        }

        return $includes;
    }

    /**
     * The declared filter keys (the `#[Filterable]` name override, else snake of the
     * property). Used by saved-filter validation/pruning.
     *
     * @param  class-string  $dataClass
     * @return list<string>
     */
    public function filterNames(string $dataClass): array
    {
        return $this->names($dataClass, Filterable::class);
    }

    /**
     * Map each declared filter key to its backing Data property — the bridge for
     * hydrating/casting a saved filter's values, since a filter key (the
     * `#[Filterable]` name override) is not always the property name (ADR-0002,
     * e.g. `tags:all` ↔ `allTags`).
     *
     * @param  class-string  $dataClass
     * @return array<string, ReflectionProperty>
     */
    public function filterProperties(string $dataClass): array
    {
        $map = [];

        foreach ($this->properties($dataClass) as $property) {
            $attribute = $this->attribute($property, Filterable::class);
            if ($attribute === null) {
                continue;
            }

            $name = $attribute->name ?? Str::snake($property->getName());
            $map[$name] = $property;
        }

        return $map;
    }

    /**
     * @param  class-string  $dataClass
     * @return list<string>
     */
    public function sortNames(string $dataClass): array
    {
        return $this->names($dataClass, Sortable::class);
    }

    /**
     * The DTO-declared default sort — the sort key of the first `#[Sortable(default: true)]`
     * property, sign-prefixed (`-key`) when its direction is `desc`. Null when no property
     * opts in.
     *
     * @deprecated A bare string cannot carry a `name → column` mapping, so a consumer
     * rebuilding an `AllowedSort` from it silently loses the declared `column` and orders by
     * the sort KEY. Correct only while `name === column`. Use {@see defaultAllowedSort()} for
     * a spatie/query-builder consumer, or {@see defaultSortColumn()} for a plain-Eloquent one.
     *
     * @param  class-string  $dataClass
     */
    public function defaultSort(string $dataClass): ?string
    {
        $declared = $this->defaultSortable($dataClass);

        if ($declared === null) {
            return null;
        }

        [$name, , $direction] = $declared;

        return $direction === 'desc' ? "-{$name}" : $name;
    }

    /**
     * The DTO-declared default sort as a real `AllowedSort` — built the same way
     * {@see allowedSorts()} builds the explicit-`?sort=` set, so the declared `column`
     * survives into the default path instead of being dropped on the way through a string.
     *
     * @param  class-string  $dataClass
     */
    public function defaultAllowedSort(string $dataClass): ?AllowedSort
    {
        $declared = $this->defaultSortable($dataClass);

        if ($declared === null) {
            return null;
        }

        [$name, $column, $direction] = $declared;

        $sort = $name === $column
            ? AllowedSort::field($name)
            : AllowedSort::field($name, $column);

        return $direction === 'desc'
            ? $sort->defaultDirection(SortDirection::Descending)
            : $sort;
    }

    /**
     * The DTO-declared default sort as a raw `['column', 'direction']` pair, for a consumer
     * that orders a plain Eloquent builder and so has nowhere to put an `AllowedSort`.
     *
     * @param  class-string  $dataClass
     * @return array{column: string, direction: 'asc'|'desc'}|null
     */
    public function defaultSortColumn(string $dataClass): ?array
    {
        $declared = $this->defaultSortable($dataClass);

        if ($declared === null) {
            return null;
        }

        [, $column, $direction] = $declared;

        return ['column' => $column, 'direction' => $direction];
    }

    /**
     * The first `#[Sortable(default: true)]` declaration, resolved to its three moving parts:
     * the sort key, the column it maps to (the key itself when none is declared), and the
     * direction. The one place the three public default-sort accessors read the attribute.
     *
     * @param  class-string  $dataClass
     * @return array{0: string, 1: string, 2: 'asc'|'desc'}|null
     */
    private function defaultSortable(string $dataClass): ?array
    {
        foreach ($this->properties($dataClass) as $property) {
            $attribute = $this->attribute($property, Sortable::class);
            if ($attribute === null || ! $attribute->default) {
                continue;
            }

            $name = $attribute->name ?? Str::snake($property->getName());

            return [
                $name,
                $attribute->column ?? $name,
                strtolower($attribute->direction) === 'desc' ? 'desc' : 'asc',
            ];
        }

        return null;
    }

    /**
     * @param  class-string  $dataClass
     * @return list<string>
     */
    public function includeNames(string $dataClass): array
    {
        return $this->names($dataClass, Includable::class);
    }

    /**
     * @param  class-string  $dataClass
     * @param  class-string  $attribute
     * @return list<string>
     */
    private function names(string $dataClass, string $attribute): array
    {
        $names = [];

        foreach ($this->properties($dataClass) as $property) {
            $declared = $this->attribute($property, $attribute);
            if ($declared === null) {
                continue;
            }

            $names[] = $declared->name ?? Str::snake($property->getName());
        }

        return $names;
    }

    /**
     * @param  class-string  $dataClass
     * @return list<ReflectionProperty>
     */
    private function properties(string $dataClass): array
    {
        return (new ReflectionClass($dataClass))->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $attribute
     * @return T|null
     */
    private function attribute(ReflectionProperty $property, string $attribute): ?object
    {
        $attrs = $property->getAttributes($attribute);

        return empty($attrs) ? null : $attrs[0]->newInstance();
    }
}
