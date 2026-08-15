<?php

namespace Rushing\DataFilters\Contracts;

use Rushing\DataFilters\Attributes\ResourceFilter;
use Rushing\DataFilters\DataFilterManager;
use Rushing\DataFilters\Registry\UnresolvableResourceModel;

/**
 * The port a host fills in so a {@see ResourceFilter} declaration can omit `model:` and
 * still resolve one (ADR-0008). This package DEFINES the seam and never binds it — that
 * is what keeps the package host-agnostic: it has no idea where a host already keeps the
 * key → model mapping, only that a host with one shouldn't have to restate it on every
 * Filter Data class.
 *
 * A host binds an implementation backed by whatever registry it already maintains. It is
 * consulted lazily, at resource-resolution time (inside
 * {@see DataFilterManager::resource()}) — never at discovery or boot
 * time — so nothing depends on which provider booted first.
 *
 * Returning `null` means "I don't know this key," not "this key is broken": the resolver
 * signals absence by returning, never by throwing. The caller decides what an unresolvable
 * model means (here: a
 * {@see UnresolvableResourceModel} naming the seam).
 */
interface ResourceModelResolver
{
    /**
     * @param  string  $resourceKey  the canonical resource key (a `#[ResourceFilter]`
     *                               alias resolves under the key it aliases, not its own)
     * @return class-string|null the Eloquent model class-string, or null if unknown
     */
    public function resolveModel(string $resourceKey): ?string;
}
