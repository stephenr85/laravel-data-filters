# Laravel Data Filters

A host-agnostic filtering spine for Laravel. Declare a resource's filterable surface
once, as attributes on a Spatie `Data` class, and derive three things from that single
declaration:

- a [`spatie/laravel-query-builder`](https://github.com/spatie/laravel-query-builder)
  query for the list endpoint,
- an `x-filter` JSON-Schema keyword for a UI filter form (via
  [`rushing/laravel-data-schemas`](https://github.com/stephenr85/laravel-data-schemas)),
- a persisted, validated saved filter.

The host owns its models, authorization scoping, and response DTOs. The package owns the
attributes, the operators, the registry, and the derivation. Nothing in `src/` names a
host model.

## Requirements

- PHP 8.3+
- Laravel 12
- `spatie/laravel-data`, `spatie/laravel-query-builder` ^7

## Installation

```bash
composer require rushing/laravel-data-filters
```

Publish the config:

```bash
php artisan vendor:publish --tag=data-filters-config
```

## Declaring a resource

A Filter Data class is a Spatie `Data` class whose properties are the resource's
queryable surface. Each operator maps a property to an `AllowedFilter` and to a control
descriptor in the generated schema.

```php
use Rushing\DataFilters\Attributes\Filterable;
use Rushing\DataFilters\Attributes\Includable;
use Rushing\DataFilters\Attributes\Sortable;
use Rushing\DataFilters\Operators\Exact;
use Rushing\DataFilters\Operators\Partial;
use Rushing\DataFilters\Operators\Range;
use Rushing\DataFilters\Operators\Set;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class AssetFilterData extends Data
{
    public function __construct(
        #[Filterable(Exact::class)]
        public string|Optional|null $status = null,

        #[Filterable(Partial::class)]
        #[Sortable]
        public string|Optional|null $name = null,

        #[Filterable(Range::class)]
        public int|Optional|null $size = null,

        #[Filterable(Set::class)]
        public AssetKind|Optional|null $kind = null,

        #[Includable]
        public array|Optional|null $owner = null,
    ) {}
}
```

Shipped operators: `Exact`, `Partial` (LIKE), `Range` (`{min,max}`), `Set` (whereIn),
`Search` (LIKE across columns), `Scope` (binds an Eloquent scope). When attributes can't
express a predicate, drop it to the Query class (below).

A filter key defaults to the snake-cased property name; pass `name:` to override it
(`#[Filterable(Set::class, name: 'tags:all')]`). The override is the bridge the saved
filter validator uses to cast a stored value back to its property's type.

## Wiring a resource

Register each resource key in `config/data-filters.php`:

```php
'resources' => [
    'asset' => [
        'data' => AssetFilterData::class,
        'query' => AssetQuery::class,
        'model' => Asset::class,
    ],
],
```

The Query class binds the Filter Data class to a model and is the escape hatch for what
attributes can't declare — authorization scoping, the default sort, and imperative
filters/sorts/includes:

```php
use Rushing\DataFilters\Query\ResourceQuery;

class AssetQuery extends ResourceQuery
{
    protected function baseQuery(Request $request): Builder
    {
        return Asset::query()->whereBelongsTo($request->user());
    }

    protected function defaultSort(): ?string
    {
        return '-created_at';
    }
}
```

## Applying filters

```php
use Rushing\DataFilters\Facades\DataFilter;

$assets = DataFilter::query('asset')->apply($request)->paginate();
```

`apply()` builds a `QueryBuilder` from the declared filters/sorts/includes (plus any the
Query class adds), scoped by `baseQuery()`, with the default sort applied. The request
shape is the standard `spatie/laravel-query-builder` one: `?filter[status]=active&sort=-name&include=owner`.

To resolve only the boolean filter set against a caller-provided base query — no auth
scoping, no default sort — use `applyFiltersTo()`. This is the seam for resolving a
resource's boundary somewhere other than its list endpoint.

## UI parity

`FilterableAttributesStrategy` projects each `#[Filterable]`/`#[Sortable]` onto the
resource's generated JSON Schema as an `x-filter`/`x-sort` keyword — operator, key, and a
control descriptor a form can render. The whole `x-*` family is stripped by
`laravel-data-schemas`' `forLlmStrict()`, so it never reaches a model contract.

Relational controls reference a host Options Source instead of inlining a finite domain.
Register one per `optionsRef`:

```php
DataFilter::options('owners', fn (?string $search) => Owner::query()
    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
    ->get()
    ->map(fn ($o) => ['value' => $o->id, 'label' => $o->name])
    ->all());
```

Backed enums and booleans inline their options automatically.

## Saved filters

`SavedFilter` stores a named `{filter, sort, include, limit}` set against a resource, with
a UUID primary key, a polymorphic owner, a visibility enum (`private`/`shared`/`public`),
and an optional polymorphic context.

`SavedFilterValidator` validates on save and tolerates on read:

- **On save** — unknown filter/sort/include keys are rejected (422); declared filter
  values are hydrated through their property's type and persisted in canonical form, so a
  wrong-typed value is rejected rather than stored raw.
- **On read/apply** — stored params are reconciled against the *current* resource and any
  key it no longer allows is silently dropped, so resource evolution never breaks a stored
  filter.

```php
$assets = DataFilter::applySaved($savedFilter)->paginate();
```

The host owns the CRUD endpoint and maps each resource to its response DTO; the package
stays agnostic about the output shape.

## License

MIT
