<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Gadget extends Model
{
    protected $table = 'gadgets';

    protected $guarded = [];

    protected $casts = [
        'flagged' => 'bool',
        'weight' => 'int',
    ];

    public function scopeFlagged(Builder $query, mixed ...$args): Builder
    {
        return $query->where('flagged', true);
    }
}
