# 0007 — Saved filters are validated through the Filter Data class on save; drift tolerated on read

- Status: Accepted
- Date: 2026-06-25

## Context

A `SavedFilter`'s `query_parameters` could be stored opaque and validated only at
apply time. But if the system is going to persist a *named* filter on a user's
behalf, it should not silently store an invalid one — and the Filter Data class is
right there, able to validate and cast (it is a Spatie `Data` class).

The countervailing concern is drift: saved filters are long-lived while resources
evolve (a filter is removed, a type tightens). Validating *strictly on read* would
turn every resource change into a migration/cleanup burden for stored rows.

## Decision

Validate on save, tolerate on read:

- **On save**, hydrate `query_parameters.filter` through the resource's Filter
  Data class, and check sorts/includes against the declared
  `#[Sortable]`/`#[Includable]` set (plus the Query class escape-hatch lists).
  Unknown filters or bad types are rejected (422); the normalized, cast values are
  persisted.
- **On read/apply**, reconcile against the *current* Filter Data class and silently
  prune any key the resource no longer allows, rather than hard-failing — so
  resource evolution never breaks an existing saved filter. The Spatie QueryBuilder
  allowed-* set is the final gate at apply.

Trade-off accepted: a reconcile-on-read step, and a save path that requires the
resource to be registered, in exchange for early feedback and the guarantee that a
filter is never *persisted* invalid. Drift is handled by pruning, not migration.

(Supersedes the initially-considered "store opaque, validate only at apply"
approach: persisting a named artifact warrants save-time validation.)
