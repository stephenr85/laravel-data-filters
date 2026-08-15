# 0008 — A resource declares itself with `#[ResourceFilter]`, and may resolve its model lazily

- Status: Accepted
- Date: 2026-08-15

## Context

Registering a Resource used to require a hand-maintained entry in
`config('data-filters.resources')` (or an imperative `DataFilter::resource()` call) — a step
fully disconnected from the Filter Data class that declares the resource's filterable
surface. Nothing enforced it, checked it, or warned when it was missing, so the failure mode
was a resource that looked completely declared and whose list endpoint threw at runtime. That
is not hypothetical: several hosts carried resources marked filterable at the declaration site
with no registry entry anywhere, and the gap was only ever found by hitting the endpoint.

The obvious fix — let the declaration site register itself — runs into two constraints:

1. **Existing hosts must not change behavior.** Their config entries are the source of truth
   today, and some of them intentionally differ from what an attribute would say.
2. **A model must not have to be restated.** Under the two-class convention (ADR-0002) a
   resource's model is usually already declared on the *sibling* class (a host's own resource
   attribute on the read Data class), under the same key. Making the Filter Data class repeat
   `model: Foo::class` re-creates the duplication this ADR exists to remove — but this package
   is host-agnostic and cannot know where a host keeps that mapping, nor when it is populated.

## Decision

**`#[ResourceFilter(key:, resource:, model:, query:)]`** — a class attribute on the Filter Data
class. The annotated class *is* the resource's `data`. It is `IS_REPEATABLE`, and that is
load-bearing: a legacy alias key is a second declaration sharing a `resource`, which is why
there is no `alias:` parameter and no static alias array.

`query: null` means "declared, but a host must still complete the wiring" — discovery reflects
the declaration and registers nothing. That is the shape for a package declaring a resource
whose concrete `ResourceQuery` lives in a different host app entirely.

Discovery (`AttributedResourceFilterDiscovery`) mirrors the fleet's existing attribute
discoverers, over `Rushing\Popcorn\Discovery\AttributedClassScanner`, driven by
`config('data-filters.discover.classes')` / `.paths`. Both default to `[]`, so nothing is
discovered until a host opts in.

### Override precedence — three tiers, discovery deliberately weakest

1. **`config('data-filters.resources')`** seeds the registry in `packageRegistered()`, and
   therefore wins *by construction*, not by a comparison anyone has to remember to write.
2. **`#[ResourceFilter]` discovery** runs in `packageBooted()` and is `has()`-guarded. It is
   strictly additive: it never overwrites a config-seeded key, nor one an earlier discovery
   pass registered.
3. **`DataFilter::resource($key, [...])`** overwrites unconditionally, whenever it is called.
   It remains the last word and the escape hatch.

A second discovered class claiming an already-discovered key with a *different* Query class
logs at debug level and loses. It does not throw: making a same-key collision a boot failure
would make filesystem scan order load-bearing in a far worse way than a first-wins rule does.

### Lazy model resolution through a port this package never binds

`Rushing\DataFilters\Contracts\ResourceModelResolver` (`resolveModel(string $resourceKey):
?string`) is defined here and **bound nowhere** here — the port/adapter split that keeps the
package host-agnostic. A host binds an implementation backed by whatever key → model registry
it already maintains.

It is consulted **at resource-resolution time**, inside `DataFilterManager::resource()`, and
never at discovery or boot time. That is the whole point: a `#[ResourceFilter]` may well be
discovered before the host registry that knows its model has finished registering, and making
that ordering load-bearing is exactly the class of silent misconfiguration this ADR removes.
By the time anyone asks for a resource, every provider has booted.

Returning `null` means "I don't know this key," never "this is broken" — the resolver signals
absence by returning, not by throwing. An unresolvable model raises
`UnresolvableResourceModel`, whose two messages distinguish *no resolver bound* from *resolver
doesn't know the key*, and both name the seam. A bare "call to `::query()` on null" would send
a reader hunting the registry for a wiring bug that is really a missing host binding.

## Consequences

- `ResourceDefinition::$model` is now nullable, and a definition that reaches a consumer has
  been through `DataFilterManager::resource()`. Read it via `requireModel()` where the
  guarantee matters; `ResourceQuery::baseQuery()` does.
- The package gains a dependency on `rushing/php-popcorn` for the scanner. It is framework-free
  (`symfony/finder` only, no `illuminate/*`), so it costs the package nothing in host-agnosticism.
- Config-driven registration is not deprecated and does not need migrating. A host converts
  resources one at a time, or never.
- Deriving filterability from registry presence — retiring the separate `filterable: true` flag
  a host's own resource attribute carries — becomes possible, but is deliberately left alone:
  it is a breaking change across every existing call site, and this mechanism should prove
  itself on real resources first.
