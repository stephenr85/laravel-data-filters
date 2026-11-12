<?php

namespace Rushing\DataFilters\Tests\Stubs;

use Schemastud\DataSchemas\Contracts\ProvidesEnumLabel;

/** localization 14 stub: an enum that provides its own English labels. */
enum TierLevel: string implements ProvidesEnumLabel
{
    case Gold = 'gold';
    case Silver = 'silver';

    public function label(): string
    {
        return match ($this) {
            self::Gold => 'Gold tier',
            self::Silver => 'Silver tier',
        };
    }
}
