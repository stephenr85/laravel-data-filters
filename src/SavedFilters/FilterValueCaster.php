<?php

namespace Rushing\DataFilters\SavedFilters;

use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use Spatie\LaravelData\Optional;

/**
 * Coerce a saved filter's raw stored value to the canonical type declared on its
 * backing Filter Data property, or reject it (ADR-0007's "bad types → 422"). The
 * property's union (`bool|Optional|null`, a backed enum, `array`, …) is the type
 * spec; `Optional` and `null` are stripped, the remaining core type drives the cast.
 * A value that can't be coerced throws {@see InvalidFilterValue}, which the validator
 * turns into a 422.
 */
class FilterValueCaster
{
    /**
     * @throws InvalidFilterValue
     */
    public function cast(ReflectionProperty $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (($enum = $this->backedEnum($property)) !== null) {
            return $this->toEnum($enum, $value)->value;
        }

        return match ($this->coreType($property)) {
            'bool' => $this->toBool($value),
            'int' => $this->toInt($value),
            'float' => $this->toFloat($value),
            'string' => $this->toString($value),
            'array' => $this->toArray($value),
            default => $value,
        };
    }

    /**
     * The property's declared core type with `Optional`/`null` stripped — the first
     * builtin among the union, or null when it can't be determined (caller leaves the
     * value untouched).
     */
    private function coreType(ReflectionProperty $property): ?string
    {
        foreach ($this->namedTypes($property) as $type) {
            $name = $type->getName();

            if ($name === 'null' || $name === Optional::class) {
                continue;
            }

            if ($type->isBuiltin()) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The backed-enum class behind the property's type, if any.
     *
     * @return class-string<BackedEnum>|null
     */
    private function backedEnum(ReflectionProperty $property): ?string
    {
        foreach ($this->namedTypes($property) as $type) {
            $name = $type->getName();

            if ($type->isBuiltin() || ! enum_exists($name)) {
                continue;
            }

            if ((new ReflectionEnum($name))->isBacked()) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<ReflectionNamedType>
     */
    private function namedTypes(ReflectionProperty $property): array
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            return [$type];
        }

        if ($type instanceof ReflectionUnionType) {
            return array_values(array_filter(
                $type->getTypes(),
                fn ($t) => $t instanceof ReflectionNamedType,
            ));
        }

        return [];
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     *
     * @throws InvalidFilterValue
     */
    private function toEnum(string $enum, mixed $value): BackedEnum
    {
        if ((is_string($value) || is_int($value)) && ($case = $enum::tryFrom($value)) !== null) {
            return $case;
        }

        throw InvalidFilterValue::for('a valid option');
    }

    /**
     * @throws InvalidFilterValue
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $token = strtolower($value);

            if (in_array($token, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($token, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        throw InvalidFilterValue::for('a boolean');
    }

    /**
     * @throws InvalidFilterValue
     */
    private function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if ((is_string($value) || is_float($value)) && is_numeric($value) && (float) $value === floor((float) $value)) {
            return (int) $value;
        }

        throw InvalidFilterValue::for('an integer');
    }

    /**
     * @throws InvalidFilterValue
     */
    private function toFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw InvalidFilterValue::for('a number');
    }

    /**
     * @throws InvalidFilterValue
     */
    private function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InvalidFilterValue::for('a string');
    }

    /**
     * Array-typed filters accept a list or a comma string (Spatie splits at apply);
     * elements must be scalar. The shape is preserved — only genuinely wrong types
     * (booleans, nested arrays) are rejected.
     *
     * @throws InvalidFilterValue
     */
    private function toArray(mixed $value): array|string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $element) {
                if ($element !== null && ! is_string($element) && ! is_int($element) && ! is_float($element)) {
                    throw InvalidFilterValue::for('a list of values');
                }
            }

            return $value;
        }

        throw InvalidFilterValue::for('a list of values');
    }
}
