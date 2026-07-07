<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Query\ResourceQuery;

class GadgetQuery extends ResourceQuery
{
    protected function defaultSort(): ?string
    {
        return '-created_at';
    }
}
