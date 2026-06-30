# 0002 — Filters are declared on a dedicated Filter Data class, not the Response DTO

- Status: Accepted
- Date: 2026-06-25

## Context

The intuitive home for filter attributes is the resource's existing Response DTO
(e.g. `AssetResponseData`). Inspecting that class shows why it is the wrong host:

- Its property names are the API-renamed output shape (`fileSize`, `createdAt`,
  `collection`), while the filters target the underlying columns (`size`,
  `created_at`, `collection_name`). Hanging `#[Filterable]` there would emit
  filter keys that are not the columns, forcing a name map.
- Query-only inputs — `search`, `has_results`, `tags` — have no representation on
  an output DTO at all.
- It mixes input/query concerns into an output contract.

Separately, `laravel-data-schemas`' strategy seam is strictly per-property, which
makes "every filter is a property" a desirable invariant (see ADR-0003).

## Decision

Each resource declares a separate **Filter Data class** whose properties *are*
the filter inputs — columnar fields and virtual inputs (`search`, range fields)
alike — each carrying `#[Filterable]`. A thin companion **Query class** binds the
Filter Data class to the Eloquent model and owns default sorts, auth scoping, and
any imperative closure filter.

Consequences: every filter is a property, so the per-property strategy alone emits
every keyword; input and output contracts stay separate; the Response DTO is
untouched. The cost is two classes per resource (Filter Data class + Query class),
which is accepted as the price of a clean, reflectable declaration.
