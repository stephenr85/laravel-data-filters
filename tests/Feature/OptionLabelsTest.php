<?php

use Rushing\DataFilters\Schema\FilterableAttributesStrategy;
use Rushing\DataFilters\Tests\Stubs\GadgetStatus;
use Rushing\DataFilters\Tests\Stubs\OptionLabelFilterData;
use Schemastud\DataSchemas\Contracts\SchemaText;
use Schemastud\DataSchemas\Generators\JsonSchemaGenerator;

/*
 * localization 14 — a filter's options read in the request's language, with no dependency on beam:
 * - a boolean's options are explicit keys in this package's own namespace, `data-filters::options.boolean.*`, with an
 *   English source file;
 * - an enum's options go through laravel-data-schemas' enum-text hook (`IdentitySchemaText::current()`), whose default
 *   is the enum's own label() when it provides labels, else the case name, as before.
 */

/** @return array<string, array<string, mixed>> */
function optionLabelProps(): array
{
    return (new JsonSchemaGenerator(['strategies' => [new FilterableAttributesStrategy]]))
        ->generate(new ReflectionClass(OptionLabelFilterData::class))['properties'];
}

/** @return list<string> */
function optionLabels(string $property): array
{
    return array_column(optionLabelProps()[$property]['x-filter']['options'], 'label');
}

it('keeps the English labels: Yes/No, raw case names for an enum without labels, and the enum\'s own labels', function (): void {
    expect(optionLabels('active'))->toBe(['Yes', 'No'])
        ->and(optionLabels('status'))->toBe(array_map(fn (GadgetStatus $c) => $c->name, GadgetStatus::cases()))
        ->and(optionLabels('tier'))->toBe(['Gold tier', 'Silver tier']);
});

it('translates the boolean options from a fixture locale through its own namespace', function (): void {
    $lang = sys_get_temp_dir().'/df-lang-'.getmypid().'-'.bin2hex(random_bytes(4));
    @mkdir("{$lang}/vendor/data-filters/es", 0777, true);
    file_put_contents("{$lang}/vendor/data-filters/es/options.php", "<?php return ['boolean' => ['true' => 'Sí', 'false' => 'No']];");
    app()->useLangPath($lang);
    app()->forgetInstance('translation.loader');
    app()->forgetInstance('translator');
    app('translator')->addNamespace('data-filters', __DIR__.'/../../lang');
    app()->setLocale('es');

    expect(optionLabels('active'))->toBe(['Sí', 'No']);
});

it('translates one enum\'s options through the bound enum-text hook', function (): void {
    app()->instance(SchemaText::class, new class implements SchemaText
    {
        public function classTitle(ReflectionClass $class, ?string $declared): ?string
        {
            return $declared;
        }

        public function classDescription(ReflectionClass $class, ?string $declared): ?string
        {
            return $declared;
        }

        public function enumTitle(string $enumClass, ?string $declared): ?string
        {
            return $declared;
        }

        public function enumCaseLabel(BackedEnum $case, string $declared): string
        {
            return ['Gold tier' => 'Nivel oro', 'Silver tier' => 'Nivel plata'][$declared] ?? $declared;
        }
    });

    expect(optionLabels('tier'))->toBe(['Nivel oro', 'Nivel plata'])
        ->and(optionLabels('status'))->toBe(array_map(fn (GadgetStatus $c) => $c->name, GadgetStatus::cases()));
});

it('adds no family dependency to its manifest', function (): void {
    $require = array_keys(json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true)['require']);

    expect(array_filter($require, fn (string $p) => str_starts_with($p, 'splicewire/')))->toBe([]);
});
