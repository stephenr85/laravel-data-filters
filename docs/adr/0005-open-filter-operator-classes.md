# 0005 — Filter operators are open classes, not a closed enum

- Status: Accepted
- Date: 2026-06-25

## Context

Every operator must map three ways at once: to a Spatie `AllowedFilter`, to a UI
form control, and to an `x-filter` keyword. A closed `enum` of operators is the
simpler model, but it cannot be extended per application, and it sits awkwardly
against the open, strategy-based substrate this package is built on
(`laravel-data-schemas`).

## Decision

An operator is a class implementing a `FilterOperator` contract:

```php
interface FilterOperator {
    public function toAllowedFilter(string $name): AllowedFilter; // query
    public function toControl(ReflectionProperty $property): array; // form control
    public function keyword(): array;                               // x-filter
}
```

- The package ships a batteries-included core: `Exact`, `Partial`, `Range`,
  `Set`, plus the companion-side `Search` and `Scope`. A host adds its own by
  implementing the interface — no central enum to edit.
- Operators are referenced by class-string through a single `#[Filterable(
  ExactOperator::class, …)]` wrapper attribute (one attribute name to document and
  grep), with operator-specific arguments passed inline.
- The PHP property's type refines the control *inside* each operator's
  `toControl()` (e.g. `Range` on `int` → numeric min/max; `Range` on a date →
  date-range; `Exact` on a backed enum → a select of its cases).

Trade-off accepted: there is no single closed vocabulary to enumerate, and a
project must wire a few operator classes, in exchange for full extensibility and
consistency with the strategy-pipeline philosophy.
