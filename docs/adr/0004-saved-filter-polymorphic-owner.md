# 0004 — SavedFilter: polymorphic owner + visibility, UUID primary key

- Status: Accepted
- Date: 2026-06-25

## Context

The prognosix app (the first consumer) scopes its stored filters (`data_scopes`)
to **organization + dataset**, with no `user_id` at all. The feature being
generalized is described as **per-user** saved filters. A reusable package cannot
hard-wire either a `User` or an `Organization` as the owner.

## Decision

The package ships a `SavedFilter` Eloquent model and migration (registered via
`PackageServiceProvider->hasMigration`, as content-engine ships its tables):

- **UUID primary key** (`HasUuids`); morph columns are UUID strings. This is the
  standing convention for package-owned models.
- A polymorphic **owner** (`owner_type`/`owner_id`) — the host points it at a
  `User`, `Organization`, or `Team`.
- A **visibility** enum (`private` | `shared` | `public`).
- An optional polymorphic **context** (`context_type`/`context_id`) that captures
  "within dataset/project X" — this is how prognosix's dataset scoping maps.
- `query_parameters` stores the Spatie `{filter, sort, include, limit}` shape —
  the same JSON prognosix already persists.
- `resource` (string key) names the target Resource; `name`, `is_default`.

Trade-off accepted: morph columns are less constrained than real foreign keys
(no DB-level FK), in exchange for serving per-user and org/dataset ownership from
one table without hard-wiring the host's models.
