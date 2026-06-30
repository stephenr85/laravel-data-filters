<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Resource Registry
    |--------------------------------------------------------------------------
    |
    | Maps a stable resource key to its wiring: the Filter Data class (the
    | declaration site for `#[Filterable]`/`#[Sortable]`/`#[Includable]`), the
    | per-resource Query class (the escape hatch binding it to a model and owning
    | auth scoping + default sort), and the Eloquent model. A host may also
    | register resources imperatively via `DataFilter::resource(...)`.
    |
    |   'fragment' => [
    |       'data'  => \App\Data\Filters\FragmentFilterData::class,
    |       'query' => \App\QueryBuilders\FragmentQuery::class,
    |       'model' => \App\Models\Fragment::class,
    |   ],
    |
    */
    'resources' => [],
];
