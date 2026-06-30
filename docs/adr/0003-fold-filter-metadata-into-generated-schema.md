# 0003 — Filter-form metadata folds into the generated schema; no endpoint

- Status: Accepted
- Date: 2026-06-25

## Context

The frontend needs a form descriptor per resource. Three constraints shape how it
is delivered:

- `laravel-data-schemas`' `SchemaStrategy` seam fires strictly per
  `ReflectionProperty`. There is no class-level hook, so a top-level
  `x-filters`/`x-sorts` block cannot be contributed by a downstream strategy.
- `GenerateSchemasAction` emits exactly one file per class (first matching
  generator wins via `break`, and the path generator yields one path). A separate
  `*.filterset.json` sidecar would require changing data-schemas or swapping the
  whole generator.
- The host prefers a typed frontend import over a package-owned HTTP endpoint.

ADR-0002 already makes every filter a property, which removes the only thing that
needed a class-level block.

## Decision

Filter-form metadata is emitted as per-property `x-filter` keywords on the
resource's existing generated `*.schema.json`, by the strategy registered in
ADR-0001. The frontend imports that schema file and reads the `x-filter` keys to
render controls.

- Zero changes to `laravel-data-schemas`; it rides the existing `schemas:generate`.
- `forLlmStrict` already strips all `x-*` keywords, so filter metadata never
  reaches an LLM-facing contract for free.
- The package ships **no** HTTP endpoint. An app that wants one may add it.

Trade-off accepted: the frontend reads JSON-Schema internals rather than a
purpose-built FilterSet type, and validation + form metadata share one file.
