/**
 * Canonical TypeScript shape of the `x-filter` / `x-sort` vendor keywords this
 * package emits.
 *
 * This file is the SINGLE SOURCE OF TRUTH for the client-side types, co-located
 * with the operators that emit them (`src/Operators/*`). It exists so a consuming
 * frontend (e.g. `@schemastud/facets`) does not hand-author — and silently drift —
 * its own copy of these types.
 *
 * The pairing is enforced from the PHP side by `FilterSchemaTypeConformanceTest`:
 * it reflects every shipped operator's emitted `control` / `operator` and asserts
 * the values stay within the vocabularies declared here (mirrored into the test's
 * `CANONICAL_CONTROLS` / `CANONICAL_OPERATORS`). Add a control in an operator and
 * that test fails until this union — and the test's mirror — are updated together.
 *
 * A future zero-hand-step single-source (emit this .ts straight from the operator
 * reflection, or generate a JSON sidecar the test reads) is a follow-on; for now the
 * conformance test is the anti-drift latch.
 */

/**
 * Every control an emitted `x-filter` can carry.
 *
 *   text          — Exact (non-finite), Partial, plain Scope
 *   select        — Exact (finite / relational), options-backed Scope (single id)
 *   multiselect   — Set
 *   search        — Search
 *   date-range    — Range over a date-typed property
 *   number-range  — Range over a numeric property
 */
export type FilterControl =
    | 'text'
    | 'select'
    | 'multiselect'
    | 'search'
    | 'date-range'
    | 'number-range';

/** An inlined finite-domain option (backed enum case, or a bool's Yes/No). */
export interface FilterInlineOption {
    value: string | number | boolean;
    label: string;
}

/**
 * The `x-filter` keyword: `{ operator, name, ...control }`.
 *
 * `operator` is a shipped operator name (`exact` | `partial` | `range` | `set` |
 * `search` | `scope`) OR a host operator name (a host may register its own, e.g.
 * `any_tags`), so it is intentionally the open `string` — the closed set below is
 * the shipped floor, not a ceiling.
 */
export interface FilterDescriptor {
    operator: string;
    /** The `filter[<name>]` query key. */
    name: string;
    control: FilterControl;
    /** Relational options: the named Options Source key the host resolves. */
    optionsRef?: string;
    valueKey?: string;
    labelKey?: string;
    searchable?: boolean;
    /** Inline finite-domain options (backed enum / bool), emitted in place of `optionsRef`. */
    options?: FilterInlineOption[];
}

/** The shipped operator names this package emits (host operators extend beyond these). */
export type ShippedFilterOperator =
    | 'exact'
    | 'partial'
    | 'range'
    | 'set'
    | 'search'
    | 'scope';

/** The `x-sort` keyword: the query field name used as `<name>` (asc) / `-<name>` (desc). */
export interface SortDescriptor {
    name: string;
}

export interface FilterSchemaProperty {
    title?: string;
    'x-filter'?: FilterDescriptor;
    'x-sort'?: SortDescriptor;
}

export interface FilterSchema {
    properties: Record<string, FilterSchemaProperty>;
}
