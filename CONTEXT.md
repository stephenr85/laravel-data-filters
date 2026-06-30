# Data Filters (package)

A host-agnostic filtering spine (`rushing/laravel-data-filters`). You declare a
resource's filterable surface once, as attributes on a Spatie `Data` class; the
package derives three things from that single declaration — a Spatie
`spatie/laravel-query-builder` query, a UI filter-form descriptor (emitted as
`x-filter` keywords on the resource's generated JSON Schema, via
`rushing/laravel-data-schemas`), and persistence for a user's saved filters. The
host owns the models, scoping, and auth; the package owns the abstractions and
the derivation.

## Language

**Filter Data class**:
A per-resource Spatie `Data` class whose properties *are* the resource's queryable
surface, declared with `#[Filterable]`, `#[Sortable]`, and `#[Includable]`. The
single declaration site the JSON Schema, the Spatie `AllowedFilter` set, the
allowed sorts, and the allowed includes are all derived from. Distinct from the
resource's Response DTO (see ADR-0002).
_Avoid_: filter DTO, criteria object, response data

**Query class**:
The thin per-resource companion to a Filter Data class — the escape hatch for what
attributes can't express. Binds the Filter Data class to an Eloquent model and
owns authorization scoping, the default sort, and any imperative (closure) filter,
sort, or include. Concrete classes keep a `*Query` suffix (`AssetQuery extends
ResourceQuery`).
_Avoid_: handler, scope type, DataScopeType, builder

**Sortable / Includable**:
Property attributes that, like `#[Filterable]`, declare a column-mapped allowed
sort or include on the Filter Data class — `#[Sortable]` emits `x-sort` so the
form's sort selector has parity; `#[Includable]` declares an allowed include
(query-facing, not a form control). Imperative sorts/includes that don't map to a
property drop to the Query class, the same escape-hatch split as filters.
_Avoid_: orderable, expandable, with

**Filter**:
One applicable predicate over a resource — a property (or virtual input such as
`search`) paired with a Filter Operator.
_Avoid_: criterion, condition, clause

**Filterable**:
The `#[Filterable(OperatorClass::class, …)]` attribute that binds a Filter
Operator to a Filter Data class property. The one attribute name to grep for.
_Avoid_: filter tag, queryable

**Filter Operator**:
A class that knows how a single filter maps three ways at once: to a Spatie
`AllowedFilter`, to a form control, and to an `x-filter` keyword. Open-core — the
package ships `Exact`, `Partial`, `Range`, `Set`, `Search`, `Scope`; a host adds
its own by implementing the interface (ADR-0005).
_Avoid_: filter type, comparator, operator enum

**Resource**:
A filterable entity identified by a string key (e.g. `asset`), wired in the
Resource Registry to its Filter Data class + Query class + model.
_Avoid_: entity, model (the model is only one part of a resource's wiring)

**Resource Registry**:
The runtime map of resource key → wiring. Seeded from
`config('data-filters.resources')` and augmentable via the `DataFilter` facade;
the bound classes self-describe their Query class and model.
_Avoid_: manifest, resolver

**Saved Filter**:
A persisted, named, owned set of filter + sort values targeting a Resource.
Ownership is a polymorphic owner + a visibility enum, with an optional
polymorphic context (ADR-0004). Stored as the Spatie
`{filter, sort, include, limit}` shape.
_Avoid_: DataScope, saved view, preset, saved search

**x-filter (keyword)**:
The JSON-Schema vendor keyword the package's strategy emits onto each filterable
property of a resource's generated schema; the frontend reads it to render the
control. Stripped from `forLlmStrict` schemas like every other `x-*` keyword, so
it never reaches an LLM contract.
_Avoid_: x-filterable, x-query

**Options Source**:
A host-registered, named provider of the selectable values for a *relational*
filter (e.g. `'organizations'`), referenced from a `#[Filterable(options: …)]`
argument and surfaced in `x-filter` as `optionsRef`. Finite domains (backed
enums, bools) skip it and inline their options instead (ADR-0006).
_Avoid_: option endpoint, lookup, source provider
