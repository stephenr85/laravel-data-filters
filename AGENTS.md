> You are in **rushing/laravel-data-filters** — a host-agnostic filtering spine for Laravel.

Declare a resource's filterable surface once on a Spatie Data class; derive a `spatie/laravel-query-builder` query, an `x-filter` JSON-Schema keyword for UI parity (via `laravel-data-schemas`), and persisted saved filters from that single declaration.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
