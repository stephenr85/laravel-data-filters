# 0006 — Filter options: inline finite domains, reference relational ones

- Status: Accepted
- Date: 2026-06-25

## Context

The generated filter form needs option lists. Two kinds of domain exist:

- **Finite, reflectable** — a backed enum (`MediaCollectionEnum`) or a `bool`.
  The values are knowable at generation time.
- **Relational / foreign-key** — `organization_id`, `project_id`,
  `customer_uuid`. The values are a dynamic, potentially large DB query that
  cannot be inlined into a committed schema file, and (per ADR-0003) the package
  ships no HTTP endpoint to serve them.

## Decision

Two paths, chosen automatically by what the property's type can tell us:

- **Finite domains inline.** A backed-enum or `bool` filter inlines its options
  directly into the `x-filter` keyword (`{ control: 'select', options: [...] }`).
  Zero configuration.
- **Relational domains reference.** The filter declares a named options source on
  the attribute — `#[Filterable(ExactOperator::class, options: 'organizations')]`.
  The `x-filter` keyword then carries
  `{ control: 'select', optionsRef: 'organizations', valueKey, labelKey,
  searchable: true }` instead of a value list. The host registers what that key
  resolves to via `DataFilter::options('organizations', …)` — a provider class or
  a reference to its own endpoint.

The package stays route-agnostic and ships no endpoint; the frontend follows the
`optionsRef` to a host-owned source with typeahead. Trade-off accepted: the host
wires each relational options source by hand, in exchange for keeping dynamic data
out of the schema file and the package free of any owned HTTP surface.

### Scope-bound relational filters

The reference path is not exclusive to column comparisons. A `Scope` filter whose
scope takes a relational id (`scopeProducedByCircuit(string $circuitId)`) declares
the same `options:` key —
`#[Filterable(Scope::class, scope: 'producedByCircuit', options: 'circuits')]`. An
options-backed scope defaults its control to `select` (a single relational id) and
emits the same `optionsRef`/`valueKey`/`labelKey`/`searchable` keyword, so the
frontend renders it as a type-ahead identical to a relational `Set` — no per-filter
control code. A scope with no `options` stays a plain `text` control as before.
