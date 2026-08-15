<?php

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
    | Entries here are seeded in the register phase and therefore WIN over anything
    | `#[ResourceFilter]` discovery finds below (ADR-0008).
    |
    */
    'resources' => [],

    /*
    |--------------------------------------------------------------------------
    | Resource Filter Discovery
    |--------------------------------------------------------------------------
    |
    | Where to look for `#[ResourceFilter]`-annotated Filter Data classes, so a
    | resource can register itself instead of being hand-listed above. `classes` is
    | an explicit list (cheap — no filesystem walk); `paths` are directories walked
    | for annotated classes. Both default to empty: nothing is discovered until a
    | host opts in, and an existing host's behavior is unchanged.
    |
    | Discovery never overwrites a key already registered above.
    |
    |   'classes' => [\App\Data\Filters\FragmentFilterData::class],
    |   'paths'   => [app_path('Data/Filters')],
    |
    */
    'discover' => [
        'classes' => [],
        'paths' => [],
    ],
];
