<?php

use Rushing\DataFilters\Keywords;
use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\GadgetFilterData;
use Rushing\LaravelDataSchemas\Generators\JsonSchemaGenerator;

/**
 * Ownership guard (JSON-LD `@context` model): this package may only EMIT `x-` extension
 * keywords it declares in {@see Keywords}. If the schema strategy ever adds an undeclared
 * `x-foo`, this fails — enforcement local to the owning package, no central list.
 */
function collectExtensionKeywords(mixed $node): array
{
    $found = [];

    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'x-')) {
                $found[] = $key;
            }

            $found = array_merge($found, collectExtensionKeywords($value));
        }
    }

    return $found;
}

it('only emits x- keywords it owns', function () {
    $schema = (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(GadgetFilterData::class));

    $emitted = array_values(array_unique(collectExtensionKeywords($schema)));
    $undeclared = array_values(array_diff($emitted, Keywords::owned()));

    expect($emitted)->not->toBe([])
        ->and($undeclared)->toBe([], sprintf(
            'FilterableAttributesStrategy emitted undeclared x- keyword(s): %s. Declare them in %s.',
            implode(', ', $undeclared),
            Keywords::class,
        ));
});
