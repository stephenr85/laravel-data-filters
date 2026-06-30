<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

enum GadgetStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
    case Draft = 'draft';
}
