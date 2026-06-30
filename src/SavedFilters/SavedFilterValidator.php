<?php

declare(strict_types=1);

namespace Rushing\DataFilters\SavedFilters;

use Illuminate\Validation\ValidationException;
use Rushing\DataFilters\DataFilterManager;
use Rushing\DataFilters\Query\ResourceQuery;

/**
 * Validate-on-save, tolerate-on-read (ADR-0007). On save, every filter/sort/include
 * key in a saved filter's `query_parameters` is checked against the resource's
 * current allowed-set (declared attributes + the Query escape-hatch lists); an
 * unknown key or a non-numeric limit is rejected (422), so a named filter is never
 * persisted invalid. On read/apply, the same params are reconciled against the
 * *current* resource and any key it no longer allows is silently dropped, so
 * resource evolution never breaks a stored filter — Spatie's allowed-set remains the
 * final gate at apply.
 */
final class SavedFilterValidator
{
    public function __construct(
        private readonly DataFilterManager $manager,
        private readonly FilterValueCaster $caster = new FilterValueCaster,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(string $resource, array $params): array
    {
        $query = $this->manager->query($resource);
        $errors = [];

        foreach (array_keys($params['filter'] ?? []) as $key) {
            if (! in_array($key, $query->filterNames(), true)) {
                $errors["query_parameters.filter.{$key}"][] = "Unknown filter [{$key}] for resource [{$resource}].";
            }
        }

        foreach ($this->tokens($params['sort'] ?? []) as $sort) {
            $field = ltrim((string) $sort, '-');
            if ($field !== '' && ! in_array($field, $query->sortNames(), true)) {
                $errors['query_parameters.sort'][] = "Unknown sort [{$field}] for resource [{$resource}].";
            }
        }

        foreach ($this->tokens($params['include'] ?? []) as $include) {
            if ($include !== '' && ! in_array($include, $query->includeNames(), true)) {
                $errors['query_parameters.include'][] = "Unknown include [{$include}] for resource [{$resource}].";
            }
        }

        if (isset($params['limit']) && ! is_numeric($params['limit'])) {
            $errors['query_parameters.limit'][] = 'The limit must be numeric.';
        }

        [$filters, $castErrors] = $this->castFilters($query, $params['filter'] ?? []);
        $errors += $castErrors;

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if (isset($params['filter'])) {
            $params['filter'] = $filters;
        }

        return $params;
    }

    /**
     * Hydrate each declared filter value through its backing Data property's type,
     * casting it to canonical form (ADR-0007). Keys backed by a closure escape-hatch
     * (no Data property) and unknown keys (already flagged above) pass through
     * untouched; a value that can't be coerced to its declared type is collected as a
     * 422 error rather than persisted raw.
     *
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function castFilters(ResourceQuery $query, mixed $filter): array
    {
        if (! is_array($filter)) {
            return [[], []];
        }

        $properties = $query->filterProperties();
        $errors = [];

        foreach ($filter as $key => $value) {
            if (! isset($properties[$key])) {
                continue;
            }

            try {
                $filter[$key] = $this->caster->cast($properties[$key], $value);
            } catch (InvalidFilterValue $e) {
                $errors["query_parameters.filter.{$key}"][] = "Filter [{$key}] {$e->getMessage()}.";
            }
        }

        return [$filter, $errors];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function prune(string $resource, array $params): array
    {
        $query = $this->manager->query($resource);

        if (isset($params['filter']) && is_array($params['filter'])) {
            $allowed = $query->filterNames();
            $params['filter'] = array_filter(
                $params['filter'],
                fn ($key) => in_array($key, $allowed, true),
                ARRAY_FILTER_USE_KEY,
            );
        }

        if (isset($params['sort'])) {
            $params['sort'] = $this->pruneTokens(
                $params['sort'],
                $query->sortNames(),
                fn ($token) => ltrim((string) $token, '-'),
            );
        }

        if (isset($params['include'])) {
            $params['include'] = $this->pruneTokens(
                $params['include'],
                $query->includeNames(),
                fn ($token) => (string) $token,
            );
        }

        return $params;
    }

    /**
     * Split a sort/include value (comma string or array) into its tokens.
     *
     * @return list<string>
     */
    private function tokens(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(fn ($v) => (string) $v, $value));
        }

        if (is_string($value) && $value !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''));
        }

        return [];
    }

    /**
     * Drop tokens whose (normalized) field is no longer allowed; preserve the input
     * shape (comma string in → comma string out).
     */
    private function pruneTokens(mixed $value, array $allowed, callable $field): mixed
    {
        $kept = array_values(array_filter(
            $this->tokens($value),
            fn ($token) => in_array($field($token), $allowed, true),
        ));

        return is_array($value) ? $kept : implode(',', $kept);
    }
}
