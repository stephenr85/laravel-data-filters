<?php

namespace Rushing\DataFilters\Operators;

use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * A virtual full-text-ish input that fans a single value out across several
 * columns as `LIKE` predicates — `filter[search]=ace` → `WHERE (name LIKE '%ace%'
 * OR body LIKE '%ace%')`. It has no single backing column; the columns to search
 * are declared on the attribute (`#[Filterable(Search::class, columns: ['name',
 * 'body'])]`). A host with a real search engine (Scout, tsvector) implements its
 * own operator instead.
 */
class Search extends Operator
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public array $columns = [],
    ) {
        parent::__construct();
    }

    protected function operatorName(): string
    {
        return 'search';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        $columns = $this->columns;

        return AllowedFilter::callback($name, function ($query, $value) use ($columns): void {
            $query->where(function ($inner) use ($columns, $value): void {
                foreach (array_values($columns) as $i => $column) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $inner->{$method}($column, 'like', '%'.$value.'%');
                }
            });
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => 'search'];
    }
}
