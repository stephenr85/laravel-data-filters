<?php

namespace Rushing\DataFilters\Registry;

use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Exceptions\RegistryMiss;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * The runtime map of resource key → wiring (ADR-0002). Seeded from
 * `config('data-filters.resources')` and augmentable at runtime via the
 * `DataFilter` facade. A stable string key is how list endpoints, saved filters,
 * and the schema strategy all resolve the same resource.
 *
 * ## It is a popcorn registry now (registry-kernel ticket 38)
 *
 * The private `[key => ResourceDefinition]` array is gone; a composed {@see BasicRegistry} holds the
 * entries under the declared `data-filters.resources` root, and every accessor this class always had
 * — {@see get()}, {@see has()}, {@see all()}, {@see registerDefinition()} — is now sugar over the
 * kernel contract rather than over an array. Composition, never a base class: this class owns domain
 * vocabulary no kernel base could supply (kernel ticket 01 D1).
 *
 * Two consequences worth knowing, because a caller can see both:
 *
 * - **A miss throws {@see RegistryMiss}** (a `RuntimeException`) where it used to throw
 *   `InvalidArgumentException`. The message names suggestions instead of the bare key.
 * - **Re-registering an existing key keeps its position in {@see all()}**, exactly as assigning into a
 *   PHP array did. This paragraph used to say the opposite, and it was right when it was written:
 *   `OnDuplicate::Supersede` displaced and appended until registry-kernel ticket 62 made supersession
 *   an override IN PLACE. Nothing here orders across resources anyway — `all()` feeds `array_keys()`
 *   for a `Rule::in` and a schema lookup — but the difference is now gone rather than merely harmless.
 *
 * ## Seeding is READ-THROUGH, and that is the migration's one behaviour change
 *
 * `describe()` forces this singleton to construct at the owner's `boot()`. A constructor that
 * snapshotted `config('data-filters.resources')` would therefore freeze the map at boot, ahead of any
 * host that finishes materialising its config later — the archetype-c trap (kernel ticket 37, and the
 * live proof is `Splicewire\Tower\Tests\TenantTestCase::rebindDataFilterRegistry()`, a whole workaround
 * for exactly this snapshot). So the constructor argument now defaults to `null` meaning *read the
 * config at first access*, and the seed happens on the first read or write instead. Passing an explicit
 * array still works and still wins, which is what keeps every existing `new ResourceRegistry([...])`
 * call honest.
 *
 * @implements Registry<ResourceDefinition>
 */
#[IsRegistry(
    root: 'data-filters.resources',
    of: 'filterable resources — one wiring (Filter Data class + Query class + model) per resource key',
    arity: RegistryArity::PickOne,
    entryType: ResourceDefinition::class,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'Three tiers over one key, weakest last: `config(\'data-filters.resources\')` seeds and wins, '
        .'`#[ResourceFilter]` discovery fills only the gaps (it `has()`-guards at the caller, ADR-0008), '
        .'and the imperative `DataFilter::resource($key, $config)` escape hatch supersedes either.',
)]
class ResourceRegistry implements Gated, Registry
{
    private BasicRegistry $entries;

    /**
     * The config map to seed from, or null meaning *read `config('data-filters.resources')` when the
     * seed is first needed*. Consumed once; see {@see seed()}.
     *
     * @var array<string, array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}>|null
     */
    private ?array $pending;

    private bool $seeded = false;

    /**
     * @param  array<string, array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}>|null  $resources
     */
    public function __construct(?array $resources = null)
    {
        $this->entries = BasicRegistry::for($this);
        $this->pending = $resources;
    }

    /**
     * Register a resource's wiring — as a config array under a key, or as an already-built
     * {@see ResourceDefinition} that carries its own key.
     *
     * The parameter is WIDENED from the contract rather than shadowing it (contravariance), so the
     * package's own one- and two-argument calls keep working alongside the kernel's four-argument one.
     *
     * @param  ResourceDefinition|array{data: class-string, query: class-string, model?: class-string|null, resource?: string|null}|null  $entry
     */
    public function register(RegistryKey|string|ResourceDefinition $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->seed();

        if ($key instanceof ResourceDefinition) {
            $entry = $key;
            $key = $key->key;
        }

        if (is_array($entry)) {
            $entry = ResourceDefinition::fromConfig((string) $key, $entry);
        }

        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    /**
     * Register an already-built definition. Attribute discovery registers this way (it
     * reflects a definition rather than assembling a config array), and like
     * {@see register()} it overwrites plainly — the `has()`-guard that makes discovery
     * strictly additive lives at the caller, not here (ADR-0008).
     */
    public function registerDefinition(ResourceDefinition $definition, ?string $by = null): void
    {
        $this->register($definition, by: $by);
    }

    public function has(RegistryKey|string $key): bool
    {
        $this->seed();

        return $this->entries->has($key);
    }

    /**
     * The wiring behind one resource key.
     *
     * @throws RegistryMiss no resource is registered under that key
     */
    public function get(string $key): ResourceDefinition
    {
        return $this->resolve($key);
    }

    /**
     * The same lookup, `null` when there is no such resource — {@see get()}'s nullable twin, on
     * Laravel's own `find()`/`findOrFail()` split.
     *
     * **This is the accessor to reach for when the key came off a request** (registry-kernel ticket
     * 61). `get()` throws, and a `RegistryMiss` escaping a controller is a 500 — which is right for a
     * key the code chose and wrong for one a user typed. A port that publishes only the throwing half
     * leaves every host either catching a kernel exception it never imported or paying a
     * `has()`-then-`get()` double lookup.
     *
     * It swallows one thing `tryResolve()` deliberately does not: a string that is not a legal
     * {@see Key} at all (`Fragments`, `a/b`, `` — uppercase and `/` are rejected, not folded). At the
     * kernel that is `InvalidRegistryKey` and correctly loud, because a declaration site spelling a key
     * wrong is a bug. From a URL segment it is the same 404 as any other unknown resource, so the shape
     * check happens here, through the kernel's own `tryParse()`, and never by relaxing the parser.
     *
     * Ambiguity still throws, exactly as `tryResolve()` promises. A miss is a question with no answer
     * and this caller opted into handling it; ambiguity is a question with several.
     */
    public function find(string $key): ?ResourceDefinition
    {
        $parsed = Key::tryParse($key);

        return $parsed === null ? null : $this->tryResolve($parsed);
    }

    /**
     * Every registered resource, keyed as the CALLER spelled it — keys go relative in and absolute
     * out (kernel ticket 20 D2), and a caller-facing map wants the caller's spelling.
     *
     * @return array<string, ResourceDefinition>
     */
    public function all(): array
    {
        $this->seed();

        $resources = [];

        foreach ($this->entries->relativeKeys() as $key) {
            $resources[$key] = $this->entries->resolve($key);
        }

        return $resources;
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        $this->seed();

        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        $this->seed();

        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        $this->seed();

        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        $this->seed();

        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        $this->seed();

        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }

    /**
     * Fold the config-declared resources in, once, on the first read or write.
     *
     * The flag is set BEFORE the loop because seeding registers, and `register()` seeds — the guard is
     * what makes that re-entry a no-op rather than a recursion. Ordering is preserved: config-declared
     * resources are registered first, so a later discovery or imperative registration supersedes them
     * exactly as it always did.
     */
    private function seed(): void
    {
        if ($this->seeded) {
            return;
        }

        $this->seeded = true;

        $resources = $this->pending ?? (function_exists('config') ? (array) config('data-filters.resources', []) : []);
        $this->pending = null;

        foreach ($resources as $key => $config) {
            $this->register($key, $config, by: 'config:data-filters.resources');
        }
    }
}
