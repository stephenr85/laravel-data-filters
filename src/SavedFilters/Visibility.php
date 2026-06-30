<?php

declare(strict_types=1);

namespace Rushing\DataFilters\SavedFilters;

/**
 * Who a saved filter is visible to (ADR-0004). `private` — only its owner;
 * `shared` — the owner's org/team (the host decides what that means via the
 * polymorphic owner/context); `public` — everyone on the resource.
 */
enum Visibility: string
{
    case Private = 'private';
    case Shared = 'shared';
    case Public = 'public';
}
