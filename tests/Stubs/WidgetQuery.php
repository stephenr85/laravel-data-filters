<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Query\ResourceQuery;

/**
 * The demo Query class. No escape-hatch needed — the default base query and the
 * attribute-declared allowed-sets are enough for the exact-match tracer.
 */
class WidgetQuery extends ResourceQuery {}
