<?php

namespace Rushing\DataFilters\SavedFilters;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A persisted, named, owned subset definition targeting a Resource (ADR-0004).
 * Stores the Spatie `{filter, sort, include, limit}` shape in `query_parameters`.
 * UUID primary key so it rides tenant sync; ownership is a polymorphic owner +
 * visibility, with an optional polymorphic context ("within dataset/project X").
 *
 * @property string $id
 * @property string $name
 * @property string $resource
 * @property array $query_parameters
 * @property Visibility $visibility
 * @property bool $is_default
 */
class SavedFilter extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'query_parameters' => 'array',
        'visibility' => Visibility::class,
        'is_default' => 'bool',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
