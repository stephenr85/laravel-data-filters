<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Query\ResourceQuery;

/**
 * No hand-written `defaultSort()` override — the DTO declaration is the only thing supplying
 * a default sort, which is exactly the path under test.
 */
class DivergentSortQuery extends ResourceQuery {}
