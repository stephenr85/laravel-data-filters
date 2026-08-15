<?php

namespace Rushing\DataFilters\Registry;

use RuntimeException;
use Rushing\DataFilters\Contracts\ResourceModelResolver;
use Rushing\DataFilters\DataFilterManager;

/**
 * Raised when a resource declares no `model` and the lazy fallback can't supply one —
 * either because the host never bound the {@see ResourceModelResolver} seam, or because it
 * did and the resolver doesn't know the key. Both messages name the seam explicitly: a
 * bare "call to `::query()` on null" would send a reader hunting through the registry for
 * a wiring bug that is really a missing host binding.
 */
class UnresolvableResourceModel extends RuntimeException
{
    public static function unboundResolver(ResourceDefinition $definition): self
    {
        return new self(sprintf(
            'Resource [%s] declares no `model` and no %s is bound. Either declare a model on '
            .'the resource (the `model:` argument of #[ResourceFilter], or the `model` key in '
            .'`config(\'data-filters.resources\')`), or bind %s in the host so the model can be '
            .'resolved from the key [%s].',
            $definition->key,
            ResourceModelResolver::class,
            ResourceModelResolver::class,
            $definition->resource,
        ));
    }

    public static function resolverReturnedNull(ResourceDefinition $definition): self
    {
        return new self(sprintf(
            'Resource [%s] declares no `model` and the bound %s returned null for the key [%s]. '
            .'Either register that key with the host registry the resolver reads, or declare the '
            .'model explicitly on the resource.',
            $definition->key,
            ResourceModelResolver::class,
            $definition->resource,
        ));
    }

    /**
     * The last-line guard for a definition that reached a consumer without ever passing
     * through {@see DataFilterManager::resource()} — the seam that
     * would have resolved it.
     */
    public static function unresolvedFor(ResourceDefinition $definition): self
    {
        return new self(sprintf(
            'Resource [%s] has no `model` bound. A definition is model-resolved by '
            .'DataFilterManager::resource()/query(); one built by hand must declare its model, '
            .'or a %s must be bound to supply it.',
            $definition->key,
            ResourceModelResolver::class,
        ));
    }
}
