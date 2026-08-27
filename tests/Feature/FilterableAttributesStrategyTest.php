<?php

use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\WidgetFilterData;
use Schemastud\DataSchemas\Generators\ChainedGenerator;
use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;

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

/**
 * The seam every other test in this file deliberately bypasses.
 *
 * The cases above hand-inject `['strategies' => [new FilterableAttributesStrategy]]`, which is the
 * right instrument for a UNIT test of the strategy — it isolates this package's output from whatever
 * else a host has configured. But it means nothing here exercises how a host actually reaches the
 * strategy: {@see \Rushing\DataFilters\ServiceProvider::registerSchemaStrategy()} APPENDS the class
 * to `config('data-schemas.strategies')` at boot, and `Generator::class` resolves to a
 * {@see \Schemastud\DataSchemas\Generators\ChainedGenerator} built from that config. The assertion
 * directly above proves only that a class-string is present in an array — not that the pipeline
 * instantiates it, orders it after the stock strategies, or runs it. If `registerSchemaStrategy()`
 * wrote the wrong config key, every test in this file would still pass.
 *
 * `canGenerate()` is checked explicitly rather than relying on `generate()`: the chain THROWS a bare
 * RuntimeException when no configured generator accepts the class, which would surface a config
 * regression as an opaque failure attributable to nothing.
 */
it('emits x-filter through the container-resolved generator a host actually uses', function () {
    $generator = app(Generator::class);
    $class = new ReflectionClass(WidgetFilterData::class);

    expect($generator)->toBeInstanceOf(ChainedGenerator::class)
        ->and($generator->canGenerate($class))->toBeTrue();

    $schema = $generator->generate($class);

    // The strategy ran, reached only via the provider's config append.
    expect($schema['properties']['color']['x-filter'])->toBe([
        'operator' => 'exact',
        'name' => 'color',
        'control' => 'text',
    ]);

    // And the host-shaped document carries the identity keys the hand-injected generator above
    // never emits, because a bare `strategies` slot suppresses nothing but also configures nothing.
    expect($schema)->toHaveKeys(['$schema', '$id']);
});
