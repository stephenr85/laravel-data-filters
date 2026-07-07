<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

/**
 * A package-internal demo model for the testbench resource. The package `src/`
 * names no host domain types; the test fixtures live here, never in `src/`.
 */
class Widget extends Model
{
    protected $table = 'widgets';

    protected $guarded = [];
}
