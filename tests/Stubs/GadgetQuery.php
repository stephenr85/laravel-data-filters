<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Query\ResourceQuery;

class GadgetQuery extends ResourceQuery
{
    protected function defaultSort(): ?string
    {
        return '-created_at';
    }
}
