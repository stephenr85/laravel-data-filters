<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Tests\Stubs;

use Rushing\DataFilters\Options\OptionsSource;

class ColorOptionsSource implements OptionsSource
{
    public function options(?string $search = null): array
    {
        $all = [
            ['value' => 'red', 'label' => 'Red'],
            ['value' => 'blue', 'label' => 'Blue'],
        ];

        if ($search === null) {
            return $all;
        }

        return array_values(array_filter(
            $all,
            fn ($o) => str_contains(strtolower($o['label']), strtolower($search)),
        ));
    }
}
