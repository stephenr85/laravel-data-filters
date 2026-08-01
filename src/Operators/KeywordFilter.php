<?php

namespace Rushing\DataFilters\Operators;

use Illuminate\Database\Eloquent\Builder;
use ReflectionProperty;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Host operator for Scout keyword search. Reuses the existing `HasCommonFilters`
 * closure: resolve matching ids through the model's Scout index, constrain by them,
 * and preserve the relevance ordering (`orderByValues`). The searchable model is
 * declared on the attribute (`#[Filterable(KeywordFilter::class, model: Fragment::class)]`).
 *
 * Extracted DOWN from the host (`App\Data\Filters\Operators\KeywordFilter`) into this
 * foundation so both the host's other filter DTOs and the beam-taxonomy cone can reach it
 * without an up-edge (tower-api-dissolution issue 17 P2). The searchable model is passed by
 * class-string, so this operator carries no dependency on any concrete model. The old FQCN is
 * kept resolving via a back-compat `class_alias` in the host until every call site migrates.
 */
class KeywordFilter extends Operator
{
    /**
     * @param  class-string  $model
     */
    public function __construct(
        public string $model,
    ) {
        parent::__construct();
    }

    protected function operatorName(): string
    {
        return 'keywords';
    }

    public function toAllowedFilter(string $name): AllowedFilter
    {
        $model = $this->model;

        return AllowedFilter::callback($name, function (Builder $q, $keywords) use ($model): void {
            $ids = $model::search($keywords)->keys();
            $q->whereIn('id', $ids);
            $q->orderByValues('id', $ids);
        });
    }

    public function toControl(ReflectionProperty $property): array
    {
        return ['control' => 'search'];
    }
}
