<?php

namespace Rushing\DataFilters\Discovery;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use ReflectionClass;
use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\Registry\ResourceDefinition;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\Popcorn\Discovery\AttributedClassScanner;

/**
 * The reflect→register machinery behind {@see ResourceFilter} — the attribute twin of a
 * hand-maintained `config('data-filters.resources')` entry (ADR-0008). Runs from the
 * service provider's boot phase over the classes and paths named in
 * `config('data-filters.discover')`, both empty by default so an existing host is
 * untouched until it opts in.
 *
 * Registration is **strictly additive**: every attempt is `has()`-guarded against the
 * registry, which by then has already been seeded from config in the register phase. That
 * is what makes the three override tiers hold — config seeds and wins, discovery fills the
 * gaps, and the imperative `DataFilter::resource()` escape hatch still overwrites either.
 *
 * A declaration whose `query` is null registers nothing (a package may declare a resource
 * whose concrete Query class lives in another host); a second discovered class claiming an
 * already-discovered key with a *different* Query class logs at debug level and loses. It
 * does not throw: turning a same-key collision into a boot failure would make filesystem
 * scan order load-bearing in a far worse way than a first-wins rule does.
 */
class AttributedResourceFilterDiscovery
{
    /**
     * Key → Query class registered by THIS discovery pass. Collisions are only interesting
     * between two discovered classes — a config-seeded key losing to nothing is the
     * override precedence working as designed, not something to warn about.
     *
     * @var array<string, class-string>
     */
    private array $discovered = [];

    public function __construct(private ResourceRegistry $registry) {}

    /**
     * Register the explicit class-strings, then everything under the discover paths
     * carrying a `#[ResourceFilter]`.
     *
     * @param  array<int, class-string>  $classes  explicit annotated class list (cheap — always honoured)
     * @param  array<int, string>  $paths  filesystem paths to scan for annotated classes
     */
    public function discover(array $classes = [], array $paths = []): void
    {
        foreach ($classes as $class) {
            $this->registerClass($class);
        }

        foreach ((new AttributedClassScanner)->scan($paths, ResourceFilter::class, instanceof: false) as $class) {
            $this->registerClass($class);
        }
    }

    /**
     * Reflect ONE class and register every `#[ResourceFilter]` it carries — all of them,
     * not just the first: the repeatable attribute is how a resource declares its legacy
     * alias keys.
     *
     * @param  class-string  $class
     */
    public function registerClass(string $class): void
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Resource filter class [{$class}] does not exist.");
        }

        $attributes = (new ReflectionClass($class))->getAttributes(ResourceFilter::class);

        if ($attributes === []) {
            throw new InvalidArgumentException("Class [{$class}] carries no #[ResourceFilter].");
        }

        foreach ($attributes as $reflected) {
            $this->register($class, $reflected->newInstance());
        }
    }

    /**
     * @param  class-string  $class
     */
    private function register(string $class, ResourceFilter $attribute): void
    {
        // Declared, but incomplete by design — a host still has to supply the Query class.
        if ($attribute->query === null) {
            return;
        }

        if (isset($this->discovered[$attribute->key]) && $this->discovered[$attribute->key] !== $attribute->query) {
            Log::debug(sprintf(
                'Duplicate #[ResourceFilter] key [%s]: %s declares query [%s] but the key is already '
                .'discovered as [%s]. The first discovery wins; the later declaration is ignored.',
                $attribute->key,
                $class,
                $attribute->query,
                $this->discovered[$attribute->key],
            ));
        }

        // Config-seeded (or already-discovered) keys are never overwritten — discovery is
        // the weakest of the three tiers.
        if ($this->registry->has($attribute->key)) {
            return;
        }

        $this->registry->registerDefinition(new ResourceDefinition(
            key: $attribute->key,
            // The annotated class IS the Filter Data class — the declaration site itself.
            data: $class,
            query: $attribute->query,
            model: $attribute->model,
            resource: $attribute->resourceKey(),
        ));

        $this->discovered[$attribute->key] = $attribute->query;
    }
}
