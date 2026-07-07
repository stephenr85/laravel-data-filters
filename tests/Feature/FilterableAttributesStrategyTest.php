<?php

use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;
use Rushing\LaravelDataSchemas\Generators\JsonSchemaGenerator;

function widgetSchema(bool $strict = false): array
{
    $generator = new JsonSchemaGenerator([
        'strategies' => [new FilterableAttributesStrategy],
    ]);

    if ($strict) {
        $generator = $generator->forLlmStrict();
    }

    return $generator->generate(new ReflectionClass(WidgetFilterData::class));
}

it('emits an x-filter keyword on a filterable property', function () {
    $props = widgetSchema()['properties'];

    expect($props['color']['x-filter'])->toBe([
        'operator' => 'exact',
        'name' => 'color',
        'control' => 'text',
    ]);
});

it('leaves a non-filterable property free of x-filter', function () {
    $name = widgetSchema()['properties']['name'];

    expect($name)->not->toHaveKey('x-filter');
});

it('strips x-filter from the llm-strict projection', function () {
    $json = json_encode(widgetSchema(strict: true));

    expect($json)->not->toContain('x-filter');
});

it('registers the strategy into the data-schemas pipeline', function () {
    expect(config('data-schemas.strategies'))->toContain(FilterableAttributesStrategy::class);
});
