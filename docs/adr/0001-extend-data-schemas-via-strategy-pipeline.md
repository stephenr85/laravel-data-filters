# 0001 — Build on laravel-data-schemas via the strategy pipeline

- Status: Accepted
- Date: 2026-06-25

## Context

A filtering feature needs two outputs that must stay in parity: a server-side
query (Spatie `spatie/laravel-query-builder`) and a UI filter form. Keeping them
in lockstep by hand is the failure mode we want to design out.

`rushing/laravel-data-schemas` already reflects Spatie `Data` classes into JSON
Schema through an ordered, per-property `SchemaStrategy` pipeline (the Scribe
pattern), configured at `config('data-schemas.strategies')`. `laravel-content-engine`
demonstrates the extension shape: it appends a strategy that projects its own
attributes (`#[Beat]`/`#[Ground]`/`#[Generate]`) to `x-*` vendor keywords without
subclassing the generator.

Forking that reflection-and-emit machinery into a standalone filtering package
would duplicate the substrate and drift from it.

## Decision

`laravel-data-filters` is a downstream **extension** of `laravel-data-schemas`,
not a standalone reimplementation:

- It depends on `rushing/laravel-data-schemas` (path-symlink `repositories`
  entry, as the sibling packages do) and adds `spatie/laravel-query-builder`.
- Its service provider idempotently appends a `SchemaStrategy` to
  `config('data-schemas.strategies')` that projects `#[Filterable]` to `x-filter`
  keywords — the exact content-engine pattern.
- The form artifact rides the existing `schemas:generate` command. No new
  generate-style command is introduced (see ADR-0003).

This buys UI parity by construction: the same declaration that builds the query
also produces the schema keywords the form renders from.
