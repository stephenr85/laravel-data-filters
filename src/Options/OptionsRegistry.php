<?php

namespace Rushing\DataFilters\Options;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Rushing\DataFilters\Registry\ResourceRegistry;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Optionality;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryArity;
use Rushing\Popcorn\Registries\RegistryKey;

/**
 * The runtime map of options-source key → provider. A provider is an
 * {@see OptionsSource} instance, its class-string (resolved through the container),
 * or a closure `fn (?string $search): array`. Resolution is lazy so a host can
 * register a class without booting it until options are actually requested.
 *
 * ## Declared, as of registry-kernel's outstanding-12 burn-down
 *
 * This is the package's second declared registry, beside {@see ResourceRegistry}. Until it declared, an
 * agent asking `popcorn:registries` where to register an options source found `data-filters.resources`
 * and nothing for options — which is how a parallel mechanism gets built beside an existing one.
 *
 * ⚠️ **`resolve()` had to change meaning, and the old meaning was renamed rather than widened.** The
 * kernel's {@see Registry::resolve()} means *give me the entry under this key*; this class's `resolve()`
 * meant *give me the OPTIONS that entry produces* — a different return type and a second argument. Those
 * are not the same question, so the old one is now {@see optionsFor()} and `resolve()` belongs to the
 * kernel. Widening was rejected for the reason `Splicewire\Beam\Surgeon\AuditScanPaths` records for its
 * own rename: a signature that still type-checks while meaning something else fails silently, and a
 * rename fails at every call site instead. Blast radius was enumerated before choosing — every caller is
 * inside this package ({@see \Rushing\DataFilters\DataFilterManager::resolveOptions()} and the provider),
 * and the estate's only other mention of this class is a comment in `splicewire/tower`'s test harness.
 *
 * `register()` and `has()` needed no rename: their kernel meanings are the ones they already had.
 */
#[IsRegistry(
    root: 'data-filters.options',
    of: 'options sources — one provider per options-source key, resolved lazily at read time',
    arity: RegistryArity::PickOne,
    onDuplicate: OnDuplicate::Supersede,
    optionality: Optionality::Optional,
    note: 'Supersede matches the behaviour this class has always had — registration was a plain array '
        .'assignment, so a second registration under one key replaced the first and nothing reported it. '
        .'The entry is deliberately un-narrowed (`mixed`): it is a union of an OptionsSource instance, '
        .'its class-string, or a closure, and the kernel types an entry with one class-string.',
    order: 20,
)]
/**
 * @implements Registry<OptionsSource|class-string<OptionsSource>|Closure>
 */
class OptionsRegistry implements Registry
{
    /** @var BasicRegistry<OptionsSource|class-string<OptionsSource>|Closure> */
    private BasicRegistry $sources;

    public function __construct(
        private Container $container,
    ) {
        $this->sources = BasicRegistry::for($this);
    }

    /**
     * Register an options source under a key.
     *
     * The kernel's signature, and it is source-compatible with every existing two-argument call: the
     * entry widens from the union to `mixed` and the return type goes from `void` to `static`, neither of
     * which a caller can notice. `$by` and `$ability` are the kernel's provenance and gate slots.
     *
     * @param  OptionsSource|class-string<OptionsSource>|Closure  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->sources->register($key, $entry, $by, $ability);

        return $this;
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->sources->has($key);
    }

    /** The registered provider under a key — the kernel's meaning of `resolve`. */
    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->sources->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->sources->tryResolve($key);
    }

    /** @return list<OptionsSource|class-string<OptionsSource>|Closure> */
    public function matches(RegistryKey|string $key): array
    {
        return $this->sources->matches($key);
    }

    /** @return list<RegistryKey> */
    public function keys(): array
    {
        return $this->sources->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->sources->unfiltered();
    }

    /**
     * The options a key's provider produces — what this class's `resolve()` used to mean.
     *
     * Behaviour is unchanged, including the exception: an unregistered key throws
     * {@see InvalidArgumentException} with the same message, because callers may already be catching it
     * and the kernel's own miss-exception is a different type carrying a different sentence.
     *
     * @return list<array{value: mixed, label: string}>
     */
    public function optionsFor(string $key, ?string $search = null): array
    {
        $source = $this->sources->tryResolve($key) ?? throw new InvalidArgumentException(
            "No options source registered for key [{$key}]."
        );

        if ($source instanceof Closure) {
            return $source($search);
        }

        if (is_string($source)) {
            $source = $this->container->make($source);
        }

        return $source->options($search);
    }
}
