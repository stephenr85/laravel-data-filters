<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Reflection;

use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Includable;
use Rushing\DataFilters\Attributes\Sortable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Reflects a Filter Data class's declared surface into the spatie/laravel-query-builder
 * allowed-sets. The same `#[Filterable]` attributes this reads are projected to
 * `x-filter` keywords by {@see \Rushing\DataFilters\Schema\FilterableAttributesStrategy};
 * one declaration site, two derived artifacts (ADR-0001).
 */
final class FilterReflector
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
     * @param  class-string  $dataClass
     * @return list<string>
     */
    public function sortNames(string $dataClass): array
    {
        return $this->names($dataClass, Sortable::class);
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
