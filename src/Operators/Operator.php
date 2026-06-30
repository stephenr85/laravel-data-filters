<?php

declare(strict_types=1);

namespace Rushing\DataFilters\Operators;

use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionProperty;
use Rushing\DataFilters\Contracts\FilterOperator;

/**
 * Shared base for the shipped operators. Implements the `keyword()` mapping in
 * terms of the operator's name + its `toControl()` output, so each concrete
 * operator only declares its query mapping, its control, and its name. The
 * `x-filter` keyword is `{ operator, name, ...control }`; `forLlmStrict` strips
 * the whole `x-*` family, so it never reaches a model contract.
 */
abstract class Operator implements FilterOperator
{
    /**
     * @param  string|null  $options  a relational Options Source key (ADR-0006); finite
     *                                 domains (backed enums, bools) inline instead.
     */
    public function __construct(
        public readonly ?string $options = null,
    ) {}

    abstract protected function operatorName(): string;

    public function keyword(ReflectionProperty $property, string $name): array
    {
        return [
            'operator' => $this->operatorName(),
            'name' => $name,
            ...$this->toControl($property),
        ];
    }

    /**
     * The backed-enum class behind the property's type, if any — used to inline a
     * finite option domain into the control (ADR-0006).
     *
     * @return class-string<BackedEnum>|null
     */
    protected function backedEnum(ReflectionProperty $property): ?string
    {
        $type = $property->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (! enum_exists($name)) {
            return null;
        }

        return (new ReflectionEnum($name))->isBacked() ? $name : null;
    }

    /**
     * The selectable values for a finite-domain control: a backed enum's cases, or
     * booleans for a `bool` property. Null when the domain is relational (the
     * control references an Options Source instead).
     *
     * @return list<array<string, mixed>>|null
     */
    protected function inlineOptions(ReflectionProperty $property): ?array
    {
        if ($enum = $this->backedEnum($property)) {
            return array_map(
                fn (BackedEnum $case) => ['value' => $case->value, 'label' => $case->name],
                $enum::cases(),
            );
        }

        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && $type->getName() === 'bool') {
            return [
                ['value' => true, 'label' => 'Yes'],
                ['value' => false, 'label' => 'No'],
            ];
        }

        return null;
    }

    /**
     * The options portion of a control: inline finite values when knowable, else an
     * `optionsRef` to a host Options Source (ADR-0006).
     *
     * @return array<string, mixed>
     */
    protected function optionsControl(ReflectionProperty $property): array
    {
        if ($this->options !== null) {
            return [
                'optionsRef' => $this->options,
                'valueKey' => 'id',
                'labelKey' => 'name',
                'searchable' => true,
            ];
        }

        if (($inline = $this->inlineOptions($property)) !== null) {
            return ['options' => $inline];
        }

        return [];
    }
}
