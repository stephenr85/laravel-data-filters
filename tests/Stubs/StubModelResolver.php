<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Contracts\ResourceModelResolver;

/**
 * A host's `ResourceModelResolver` in miniature: a key → model map standing in for
 * whatever registry a real host already keeps. Returns null for unknown keys, never throws
 * — absence is a return value in this contract.
 */
class StubModelResolver implements ResourceModelResolver
{
    /**
     * @param  array<string, class-string>  $models
     */
    public function __construct(private array $models = []) {}

    public function resolveModel(string $resourceKey): ?string
    {
        return $this->models[$resourceKey] ?? null;
    }
}
