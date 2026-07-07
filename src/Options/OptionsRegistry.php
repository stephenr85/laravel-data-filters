<?php

namespace Rushing\DataFilters\Options;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * The runtime map of options-source key → provider. A provider is an
 * {@see OptionsSource} instance, its class-string (resolved through the container),
 * or a closure `fn (?string $search): array`. Resolution is lazy so a host can
 * register a class without booting it until options are actually requested.
 */
class OptionsRegistry
{
    /** @var array<string, OptionsSource|class-string<OptionsSource>|Closure> */
    private array $sources = [];

    public function __construct(
        private Container $container,
    ) {}

    /**
     * @param  OptionsSource|class-string<OptionsSource>|Closure  $source
     */
    public function register(string $key, OptionsSource|string|Closure $source): void
    {
        $this->sources[$key] = $source;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public function resolve(string $key, ?string $search = null): array
    {
        $source = $this->sources[$key] ?? throw new InvalidArgumentException(
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
